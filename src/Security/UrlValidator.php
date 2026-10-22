<?php

declare(strict_types=1);

namespace App\Security;

use App\Support\Str;

/**
 * KIEM TRA URL TRUOC KHI LAY NOI DUNG (Chuc nang 1D).
 *
 * Chuoi kiem tra:
 *   1. Rong / do dai qua
 *   2. Dinh dang, scheme (chi http/https)
 *   3. Host hop le, khong chua ky tu dac biet
 *   4. Port hop le
 *   5. Ten mien co nam trong danh sach cho phep
 *   6. Phan giai DNS -> kiem tra IP thuc te (chan SSRF/DNS rebinding)
 *   7. IP co nam trong CIDR cho phep
 *
 * Phai kiem tra LAI sau moi lan chuyen huong (xem HttpFetcher).
 */
final class UrlValidator
{
    private AllowList $allowList;

    public function __construct(?AllowList $allowList = null)
    {
        $this->allowList = $allowList ?? AllowList::fromConfig();
    }

    public function allowList(): AllowList
    {
        return $this->allowList;
    }

    public function validate(string $url, bool $resolveDns = true): UrlValidationResult
    {
        $url = trim($url);

        if ($url === '') {
            return UrlValidationResult::denied('empty', 'Địa chỉ URL không được để trống.');
        }

        if (mb_strlen($url, 'UTF-8') > 2048) {
            return UrlValidationResult::denied('too_long', 'Địa chỉ URL vượt quá độ dài cho phép (tối đa 2048 ký tự).');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return UrlValidationResult::denied('control_chars', 'Địa chỉ URL chứa ký tự điều khiển không hợp lệ.');
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'])) {
            return UrlValidationResult::denied('malformed', 'Địa chỉ URL không đúng định dạng.');
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme === '') {
            return UrlValidationResult::denied('malformed', 'Địa chỉ URL thiếu giao thức.');
        }

        // Tu choi file://, ftp://, gopher://, data:// ...
        if (!$this->allowList->isSchemeAllowed($scheme)) {
            $supported = implode(', ', $this->allowList->allowedSchemes());

            return UrlValidationResult::denied(
                'scheme_not_allowed',
                "Giao thức \"{$scheme}\" không được hỗ trợ. Chỉ chấp nhận: {$supported}.",
            );
        }

        if (!isset($parts['host']) || $parts['host'] === '') {
            return UrlValidationResult::denied('malformed', 'Địa chỉ URL không có tên miền hợp lệ.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host === '' || !$this->isValidHost($host)) {
            return UrlValidationResult::denied('invalid_host', 'Tên miền trong URL không hợp lệ.');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            return UrlValidationResult::denied('invalid_port', 'Cổng trong URL không hợp lệ.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return UrlValidationResult::denied('credentials_in_url', 'URL không được chứa thông tin đăng nhập.');
        }

        if (!$this->allowList->isPortAllowed($port, $scheme)) {
            $allowed = implode(', ', array_map('strval', $this->allowList->allowedPorts())) ?: '(mọi cổng)';

            return UrlValidationResult::denied(
                'port_not_allowed',
                "Cổng không thuộc danh sách cho phép. Các cổng cho phép: {$allowed}.",
            );
        }

        if (!$this->allowList->matchesHost($host)) {
            return UrlValidationResult::denied(
                'host_not_allowed',
                "Miền \"{$host}\" không nằm trong danh sách địa chỉ được phép lưu.",
            );
        }

        $normalized = Str::normalizeUrl($url);
        $effectivePort = $port ?? ($scheme === 'https' ? 443 : 80);

        if (!$resolveDns) {
            return UrlValidationResult::allowed($normalized, $scheme, $host, $effectivePort, []);
        }

        $ips = $this->resolveHost($host);
        if ($ips === []) {
            return UrlValidationResult::denied('dns_failed', "Không phân giải được tên miền \"{$host}\".");
        }

        $allowedIps = [];
        foreach ($ips as $ip) {
            $reasons = IpRange::blockedReasons($ip);

            if ($reasons !== [] && !$this->allowList->allowsPrivateNetwork()) {
                $why = implode(', ', $reasons);

                return UrlValidationResult::denied(
                    'blocked_ip',
                    "Địa chỉ \"{$ip}\" thuộc vùng bị chặn ({$why}).",
                );
            }

            if (!$this->allowList->ipAllowed($ip)) {
                return UrlValidationResult::denied(
                    'ip_not_allowed',
                    "Địa chỉ IP \"{$ip}\" không thuộc dải cho phép.",
                );
            }

            $allowedIps[] = $ip;
        }

        return UrlValidationResult::allowed($normalized, $scheme, $host, $effectivePort, $allowedIps);
    }

    /** @return array<int,string> */
    public function resolveHost(string $host): array
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = [];
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = $record['ip'];
                } elseif (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            $resolved = gethostbynamel($host);
            if (is_array($resolved)) {
                $ips = $resolved;
            }
        }

        return array_values(array_unique($ips));
    }

    private function isValidHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false) {
            return strlen($host) <= 253;
        }

        return false;
    }
}