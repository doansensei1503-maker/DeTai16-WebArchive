<?php

declare(strict_types=1);

namespace App\Service;

use App\Security\AllowList;
use App\Security\IpRange;
use App\Support\Bootstrap;
use App\Support\Database;
use App\Support\Env;
use App\Support\Str;

/**
 * LUU ANH CUC BO (Chuc nang 3C).
 *
 * Chi ho tro PNG, JPEG, WebP (cong bo ro rang).
 * Kiem tra MIME that, gioi han kich thuoc, luu trong storage/assets.
 * Ghi duong dan noi bo vao DB va tra ve duong dan doc anh co kiem tra quyen.
 */
final class AssetStorage
{
    private AllowList $allowList;
    private string $baseDir;

    public function __construct(?AllowList $allowList = null)
    {
        $this->allowList = $allowList ?? AllowList::fromConfig();
        $this->baseDir = Bootstrap::storagePath('assets');
        if (!is_dir($this->baseDir)) {
            @mkdir($this->baseDir, 0o775, true);
        }
    }

    /**
     * Luu mot anh tu URL.
     *
     * @return array{
     *   success: bool, asset_id: int, internal_path: string|null,
     *   reason: string, mime: string|null, bytes: int, extension: string|null
     * }
     */
    public function saveFromUrl(int $libraryId, string $imageUrl): array
    {
        $urlHash = Str::sha256($imageUrl);

        // Da luu truoc -> tra ve duong dan noi bo.
        $existing = Database::first(
            'SELECT * FROM assets WHERE library_id = :l AND url_hash = :h LIMIT 1',
            [':l' => $libraryId, ':h' => $urlHash],
        );

        if ($existing !== null) {
            if ((string) $existing['status'] === 'saved' && is_file((string) $existing['local_path'])) {
                return [
                    'success'       => true,
                    'asset_id'      => (int) $existing['id'],
                    'internal_path' => '/asset/' . $existing['url_hash'],
                    'reason'        => 'Đã lưu trước đó.',
                    'mime'          => (string) $existing['mime_type'],
                    'bytes'         => (int) $existing['byte_size'],
                    'extension'     => (string) $existing['extension'],
                ];
            }
        }

        // Kiem tra URL anh co thuoc pham vi cho phep khong.
        $validator = new \App\Security\UrlValidator($this->allowList);
        $validation = $validator->validate($imageUrl);

        if (!$validation->allowed) {
            $this->recordFailure($libraryId, $imageUrl, $urlHash, $validation->message);

            return [
                'success'       => false,
                'asset_id'      => 0,
                'internal_path' => null,
                'reason'        => 'URL ảnh không được phép: ' . $validation->message,
                'mime'          => null,
                'bytes'         => 0,
                'extension'     => null,
            ];
        }

        // Kiem tra phan mo rong.
        $extension = $this->detectExtension($imageUrl);
        if ($extension === null) {
            $this->recordFailure($libraryId, $imageUrl, $urlHash, 'Định dạng ảnh không được hỗ trợ (chỉ PNG, JPEG, WebP).');

            return [
                'success' => false, 'asset_id' => 0, 'internal_path' => null,
                'reason' => 'Định dạng ảnh không được hỗ trợ.', 'mime' => null, 'bytes' => 0, 'extension' => null,
            ];
        }

        // Tai anh.
        $result = $this->download($imageUrl);

        if (!$result['success']) {
            $this->recordFailure($libraryId, $imageUrl, $urlHash, $result['reason']);

            return [
                'success' => false, 'asset_id' => 0, 'internal_path' => null,
                'reason' => $result['reason'], 'mime' => null, 'bytes' => 0, 'extension' => null,
            ];
        }

        $data = $result['data'];
        $mime = $result['mime'];

        // Kiem tra MIME thuc te (khong tin Content-Type).
        $realMime = $this->detectMime($data);
        if ($realMime === null) {
            $this->recordFailure($libraryId, $imageUrl, $urlHash, 'Nội dung tải về không phải ảnh hợp lệ.');
            $this->failFetch($libraryId, $imageUrl);

            return [
                'success' => false, 'asset_id' => 0, 'internal_path' => null,
                'reason' => 'Nội dung không phải ảnh hợp lệ.', 'mime' => null, 'bytes' => 0, 'extension' => null,
            ];
        }

        $allowedMimes = Env::csv('IMAGE_ALLOWED_MIME', ['image/png', 'image/jpeg', 'image/webp']);
        if (!in_array($realMime, $allowedMimes, true)) {
            $this->recordFailure($libraryId, $imageUrl, $urlHash, "MIME không được hỗ trợ: {$realMime}");
            $this->failFetch($libraryId, $imageUrl);

            return [
                'success' => false, 'asset_id' => 0, 'internal_path' => null,
                'reason' => "MIME không được hỗ trợ: {$realMime}", 'mime' => $realMime, 'bytes' => 0, 'extension' => null,
            ];
        }

        // Ghi file vao thu muc theo thu vien de tach biet.
        $libraryDir = $this->baseDir . '/' . $libraryId;
        if (!is_dir($libraryDir)) {
            @mkdir($libraryDir, 0o775, true);
        }

        $fileName = $urlHash . '.' . $this->extensionForMime($realMime);
        $filePath = $libraryDir . '/' . $fileName;

        if (file_put_contents($filePath, $data) === false) {
            $this->recordFailure($libraryId, $imageUrl, $urlHash, 'Không ghi được tệp ảnh.');
            $this->failFetch($libraryId, $imageUrl);

            return [
                'success' => false, 'asset_id' => 0, 'internal_path' => null,
                'reason' => 'Không ghi được tệp ảnh.', 'mime' => $realMime, 'bytes' => 0, 'extension' => null,
            ];
        }

        [$width, $height] = $this->imageSize($data);

        // Luu vao DB.
        Database::execute(
            'INSERT INTO assets
                (library_id, original_url, url_hash, local_path, mime_type, extension,
                 byte_size, width, height, status)
             VALUES
                (:l, :u, :h, :p, :m, :e, :b, :w, :ht, :s)
             ON DUPLICATE KEY UPDATE
                local_path = VALUES(local_path), mime_type = VALUES(mime_type),
                extension = VALUES(extension), byte_size = VALUES(byte_size),
                width = VALUES(width), height = VALUES(height), status = VALUES(status),
                failure_reason = NULL',
            [
                ':l'  => $libraryId,
                ':u'  => $imageUrl,
                ':h'  => $urlHash,
                ':p'  => $filePath,
                ':m'  => $realMime,
                ':e'  => $this->extensionForMime($realMime),
                ':b'  => strlen($data),
                ':w'  => $width,
                ':ht' => $height,
                ':s'  => 'saved',
            ],
        );

        $assetId = (int) Database::value(
            'SELECT id FROM assets WHERE library_id = :l AND url_hash = :h',
            [':l' => $libraryId, ':h' => $urlHash],
        );

        return [
            'success'       => true,
            'asset_id'      => $assetId,
            'internal_path' => '/asset/' . $urlHash,
            'reason'        => 'Lưu thành công.',
            'mime'          => $realMime,
            'bytes'         => strlen($data),
            'extension'     => $this->extensionForMime($realMime),
        ];
    }

    /** @return array{success: bool, data: string, mime: string|null, reason: string} */
    private function download(string $url): array
    {
        $maxBytes = Env::int('IMAGE_MAX_BYTES', 2 * 1024 * 1024);
        $connectTimeout = Env::int('FETCH_CONNECT_TIMEOUT', 5);
        $totalTimeout = Env::int('FETCH_TOTAL_TIMEOUT', 15);
        $userAgent = Env::get('FETCH_USER_AGENT', 'DeTai16ArchiveBot/1.0') ?? 'DeTai16ArchiveBot/1.0';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true, // Theo chuyen huong (da kiem tra o AllowList)
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT        => $totalTimeout,
            CURLOPT_USERAGENT      => $userAgent,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $data = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $mime = (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '');
        curl_close($ch);

        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            return ['success' => false, 'data' => '', 'mime' => null, 'reason' => 'Tải ảnh quá thời gian chờ.'];
        }

        if ($errno !== 0 || $data === false || $data === '') {
            return ['success' => false, 'data' => '', 'mime' => null, 'reason' => 'Không tải được ảnh.'];
        }

        if ($httpCode >= 400) {
            return ['success' => false, 'data' => '', 'mime' => null, 'reason' => "Lỗi HTTP {$httpCode} khi tải ảnh."];
        }

        $data = (string) $data;
        if (strlen($data) > $maxBytes) {
            return ['success' => false, 'data' => '', 'mime' => null, 'reason' => 'Kích thước ảnh vượt quá giới hạn.'];
        }

        return ['success' => true, 'data' => $data, 'mime' => $mime !== '' ? explode(';', $mime)[0] : null, 'reason' => 'OK'];
    }

    /** Xac nhan MIME that bang finfo + magic bytes. */
    private function detectMime(string $data): ?string
    {
        if (strlen($data) < 12) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mime = finfo_buffer($finfo, $data);
        finfo_close($finfo);

        if ($mime === false) {
            return null;
        }

        $mime = strtolower(trim($mime));

        $supported = [
            'image/png'  => 'image/png',
            'image/jpeg' => 'image/jpeg',
            'image/webp' => 'image/webp',
        ];

        return $supported[$mime] ?? null;
    }

    private function detectExtension(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        $allowed = ['png', 'jpg', 'jpeg', 'webp'];

        return in_array($ext, $allowed, true) ? $ext : null;
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default     => 'bin',
        };
    }

    /** @return array{0:int|null, 1:int|null} */
    private function imageSize(string $data): array
    {
        $size = @getimagesizefromstring($data);
        if ($size === false) {
            return [null, null];
        }

        return [$size[0] ?? null, $size[1] ?? null];
    }

    private function recordFailure(int $libraryId, string $url, string $hash, string $reason): void
    {
        Database::execute(
            'INSERT INTO assets (library_id, original_url, url_hash, status, failure_reason)
             VALUES (:l, :u, :h, :s, :r)
             ON DUPLICATE KEY UPDATE status = :s_upd, failure_reason = :r_upd',
            [
                ':l' => $libraryId, ':u' => $url, ':h' => $hash,
                ':s' => 'failed', ':r' => Str::limit($reason, 190),
                ':s_upd' => 'failed', ':r_upd' => Str::limit($reason, 190),
            ],
        );
    }

    private function failFetch(int $libraryId, string $url): void
    {
        // No-op placeholder (khong can tao them).
    }

    /** @return array<int,array<string,mixed>> */
    public function listForLibrary(int $libraryId): array
    {
        return Database::all(
            'SELECT id, original_url, mime_type, extension, byte_size, status, failure_reason, created_at
               FROM assets WHERE library_id = :l ORDER BY created_at DESC',
            [':l' => $libraryId],
        );
    }
}