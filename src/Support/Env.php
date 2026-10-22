<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Doc cau hinh tu file .env (khong can thu vien ngoai).
 * Cho phep ghi de bang bien moi truong cua he thong.
 */
final class Env
{
    /** @var array<string,string>|null */
    private static ?array $data = null;

    public static function load(string $envFile): void
    {
        $data = [];

        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos === false) {
                    continue;
                }
                $key = trim(substr($line, 0, $pos));
                $value = trim(substr($line, $pos + 1));
                // Bo dau ngoai kep theo kieu "abc"
                if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
                    $value = substr($value, 1, -1);
                }
                $data[$key] = $value;
            }
        }

        self::$data = $data;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $envValue = getenv($key);
        if ($envValue !== false && $envValue !== '') {
            return $envValue;
        }

        $value = self::$data[$key] ?? null;
        if ($value !== null && $value !== '') {
            return $value;
        }

        return $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $raw = self::get($key);
        if ($raw === null) {
            return $default;
        }

        return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $raw = self::get($key);

        return $raw === null ? $default : (int) $raw;
    }

    /** @return array<int,string> */
    public static function csv(string $key, array $default = []): array
    {
        $raw = self::get($key);
        if ($raw === null || trim($raw) === '') {
            return $default;
        }

        $parts = array_map('trim', explode(',', $raw));

        return array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
    }
}