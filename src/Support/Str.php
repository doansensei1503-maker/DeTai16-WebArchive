<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tien ich dung chung: slug, hash, chuan hoa chuoi, thoat HTML.
 */
final class Str
{
    public static function slug(string $value): string
    {
        // Giu chu tieng Viet/Unicode thanh dang ascii de doc duoc
        $value = self::toAscii($value);
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';

        return trim($value, '-');
    }

    public static function toAscii(string $value): string
    {
        $map = [
            'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
            'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
            'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'ê' => 'e', 'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
            'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
            'ô' => 'o', 'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
            'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ư' => 'u', 'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
            'đ' => 'd', 'Đ' => 'D',
        ];

        return strtr($value, $map);
    }

    public static function excerpt(string $text, int $length = 180): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . '…';
    }

    public static function limit(string $value, int $max): string
    {
        if (mb_strlen($value, 'UTF-8') <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max, 'UTF-8');
    }

    public static function sha256(string $value): string
    {
        return hash('sha256', $value);
    }

    /** Chuan hoa URL de so sanh trung: lowercase host, bo port mac dinh, sap xep query. */
    public static function normalizeUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if ($parts === false || !isset($parts['host'])) {
            return trim($url);
        }

        $scheme = strtolower($parts['scheme'] ?? 'http');
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;
        $isDefaultPort = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
        $authority = $isDefaultPort || $port === null ? $host : $host . ':' . $port;

        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }

        $normalized = $scheme . '://' . $authority . $path;

        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $query);
            ksort($query);
            $normalized .= '?' . http_build_query($query);
        }

        return $normalized;
    }
}