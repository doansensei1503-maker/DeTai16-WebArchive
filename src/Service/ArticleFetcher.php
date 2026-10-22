<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\FetchStatus;
use App\Security\UrlValidator;

/**
 * BO LAY HTML (Chuc nang 2A).
 *
 * Dung cURL, tu kiem tra tung lan chuyen huong, kiem tra lai DNS/IP de
 * chan SSRF va DNS rebinding. Chi lay trang HTML tinh cong khai hoac
 * may chu thu nghiem cua nhom.
 */
final class ArticleFetcher
{
    private UrlValidator $validator;

    public function __construct(?\App\Security\AllowList $allowList = null)
    {
        $this->validator = new UrlValidator($allowList);
    }

    /**
     * @return array{
     *   success: bool,
     *   status: FetchStatus,
     *   message: string,
     *   final_url?: string,
     *   http_code?: int,
     *   title?: string,
     *   content_html?: string,
     *   content_text?: string,
     *   excerpt?: string,
     *   author?: string,
     *   published_at?: string|null,
     *   language?: string,
     *   layout_used?: string,
     *   redirect_chain?: string,
     *   duration_ms?: int
     * }
     */
    public function fetch(string $url, int $logId = 0, int $libraryId = 0, int $userId = 0): array
    {
        $startTime = microtime(true);

        // Kiem tra URL lan dau.
        $validation = $this->validator->validate($url);
        if (!$validation->allowed) {
            return $this->fail(FetchStatus::NotAllowed, $validation->message, null, null);
        }

        $allowList = $this->validator->allowList();

        $currentUrl = $validation->normalizedUrl ?? $url;
        $redirectChain = [];
        $maxRedirects = \App\Support\Env::int('FETCH_MAX_REDIRECTS', 5);
        $maxBytes = \App\Support\Env::int('FETCH_MAX_BYTES', 2 * 1024 * 1024);
        $connectTimeout = \App\Support\Env::int('FETCH_CONNECT_TIMEOUT', 5);
        $totalTimeout = \App\Support\Env::int('FETCH_TOTAL_TIMEOUT', 15);
        $userAgent = \App\Support\Env::get('FETCH_USER_AGENT', 'DeTai16ArchiveBot/1.0') ?? 'DeTai16ArchiveBot/1.0';

        $httpCode = null;

        // Vong lap: theo doi chuyen huong thu cong.
        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            // Kiem tra lai URL truoc moi ket noi (chong DNS rebinding).
            $hopValidation = $this->validator->validate($currentUrl);
            if (!$hopValidation->allowed) {
                return $this->fail(
                    FetchStatus::NotAllowed,
                    'Chuyển hướng dẫn tới địa chỉ không được phép: ' . $hopValidation->message,
                    $currentUrl,
                    null,
                    $redirectChain,
                );
            }

            $redirectChain[] = $currentUrl;

            $result = $this->request(
                $hopValidation->normalizedUrl ?? $currentUrl,
                $maxBytes,
                $connectTimeout,
                $totalTimeout,
                $userAgent,
                $allowList,
            );

            // Phan biet loi.
            if ($result['timeout']) {
                return $this->fail(
                    FetchStatus::Timeout,
                    'Yêu cầu vượt quá thời gian chờ (' . $totalTimeout . ' giây).',
                    $currentUrl,
                    null,
                    $redirectChain,
                    (int) ((microtime(true) - $startTime) * 1000),
                );
            }

            if ($result['connect_error']) {
                return $this->fail(
                    FetchStatus::FetchFailed,
                    'Không kết nối được tới máy chủ: ' . $result['message'],
                    $currentUrl,
                    null,
                    $redirectChain,
                    (int) ((microtime(true) - $startTime) * 1000),
                );
            }

            $httpCode = $result['http_code'];

            // Chuyen huong -> kiem tra dich tiep theo.
            if ($result['is_redirect']) {
                $location = $result['redirect_location'];
                if ($location === null) {
                    return $this->fail(
                        FetchStatus::HttpError,
                        "Máy chủ trả về {$httpCode} nhưng không có tiêu đề Location.",
                        $currentUrl,
                        $httpCode,
                        $redirectChain,
                    );
                }

                $nextUrl = $this->resolveRedirect($currentUrl, $location);
                $redirectChain[] = $location;
                $currentUrl = $nextUrl;
                continue;
            }

            // Loi HTTP 4xx / 5xx.
            if ($httpCode !== null && $httpCode >= 400) {
                return $this->fail(
                    FetchStatus::HttpError,
                    "Máy chủ trả về mã lỗi HTTP {$httpCode}.",
                    $currentUrl,
                    $httpCode,
                    $redirectChain,
                    (int) ((microtime(true) - $startTime) * 1000),
                );
            }

            // OK -> trich xuat noi dung.
            $html = $result['body'];
            if ($html === '') {
                return $this->fail(
                    FetchStatus::NoArticle,
                    'Phản hồi HTML rỗng.',
                    $currentUrl,
                    $httpCode,
                    $redirectChain,
                    (int) ((microtime(true) - $startTime) * 1000),
                );
            }

            $extraction = $this->extract($html, $hopValidation->normalizedUrl ?? $currentUrl);

            if (!$extraction['success']) {
                return $this->fail(
                    $extraction['status'],
                    $extraction['message'],
                    $currentUrl,
                    $httpCode,
                    $redirectChain,
                    (int) ((microtime(true) - $startTime) * 1000),
                );
            }

            $extraction['success'] = true;
            $extraction['status'] = FetchStatus::Processing;
            $extraction['final_url'] = $hopValidation->normalizedUrl ?? $currentUrl;
            $extraction['http_code'] = $httpCode;
            $extraction['redirect_chain'] = implode("\n", $redirectChain);
            $extraction['duration_ms'] = (int) ((microtime(true) - $startTime) * 1000);

            return $extraction;
        }

        // Vuot qua gioi han chuyen huong.
        return $this->fail(
            FetchStatus::FetchFailed,
            "Quá số lần chuyển hướng cho phép ({$maxRedirects}).",
            $currentUrl,
            $httpCode,
            $redirectChain,
            (int) ((microtime(true) - $startTime) * 1000),
        );
    }

    /**
     * Thuc hien mot request HTTP GET bang cURL.
     *
     * @return array{
     *   http_code: int|null,
     *   is_redirect: bool,
     *   redirect_location: string|null,
     *   body: string,
     *   timeout: bool,
     *   connect_error: bool,
     *   message: string
     * }
     */
    private function request(
        string $url,
        int $maxBytes,
        int $connectTimeout,
        int $totalTimeout,
        string $userAgent,
        \App\Security\AllowList $allowList,
    ): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false, // Tu quan ly chuyen huong
            CURLOPT_HEADER         => false,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT        => $totalTimeout,
            CURLOPT_USERAGENT      => $userAgent,
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml',
                'Accept-Language: vi,en;q=0.8',
                'Connection: close',
            ],
            // Gioi han kich thuoc phan hoi.
            CURLOPT_BUFFERSIZE      => 65536,
            CURLOPT_NOPROGRESS      => false,
            CURLOPT_NOPROGRESS      => false,
            CURLOPT_HEADERFUNCTION  => function ($ch, $header) use ($maxBytes): int {
                if (str_starts_with($header, 'Location:')) {
                    $GLOBALS['__detai16_location'] = trim(substr($header, 9));
                }

                return strlen($header);
            },
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        $isTimeout = in_array($errno, [
            CURLE_OPERATION_TIMEOUTED,
            CURLE_COULDNT_CONNECT => false,
        ], true);

        curl_close($ch);

        $body = $body === false ? '' : (string) $body;

        // Gioi han kich thuoc that te.
        if (strlen($body) > $maxBytes) {
            $body = substr($body, 0, $maxBytes);
        }

        $isRedirect = $httpCode >= 300 && $httpCode < 400;
        $location = $isRedirect ? ($GLOBALS['__detai16_location'] ?? null) : null;
        $GLOBALS['__detai16_location'] = null;

        return [
            'http_code'         => $httpCode,
            'is_redirect'       => $isRedirect,
            'redirect_location' => $location,
            'body'              => $body,
            'timeout'           => $errno === CURLE_OPERATION_TIMEDOUT,
            'connect_error'     => $errno === CURLE_COULDNT_CONNECT || $errno === CURLE_COULDNT_RESOLVE_HOST,
            'message'           => $error,
        ];
    }

    /** Chuyen hop URL tuong doi thanh URL tuyet doi. */
    private function resolveRedirect(string $baseUrl, string $location): string
    {
        $location = trim($location);

        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $basePath = $parts['path'] ?? '/';

        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$host}{$port}{$location}";
        }

        // Duong dan tuong doi.
        $dir = rtrim(dirname($basePath), '/');
        return "{$scheme}://{$host}{$port}{$dir}/{$location}";
    }

    /**
     * @return array{success: bool, status: FetchStatus, message: string}
     */
    private function extract(string $html, string $url): array
    {
        return ArticleExtractor::extract($html, $url);
    }

    /** @return array{success: bool, status: FetchStatus, message: string} */
    private function fail(
        FetchStatus $status,
        string $message,
        ?string $finalUrl,
        ?int $httpCode,
        array $redirectChain = [],
        ?int $durationMs = null,
    ): array {
        return [
            'success'         => false,
            'status'          => $status,
            'message'         => $message,
            'final_url'       => $finalUrl,
            'http_code'       => $httpCode,
            'redirect_chain'  => $redirectChain !== [] ? implode("\n", $redirectChain) : null,
            'duration_ms'     => $durationMs,
        ];
    }
}