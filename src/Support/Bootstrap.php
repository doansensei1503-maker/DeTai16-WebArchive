<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Hop nhat moi truong + autoload. Moi entry point (public/index.php,
 * bin/console.php, tests/bootstrap.php) deu goi ham nay.
 */
final class Bootstrap
{
    private static bool $booted = false;

    public static function boot(?string $basePath = null): void
    {
        if (self::$booted) {
            return;
        }

        $basePath = $basePath ?? dirname(__DIR__, 2);

        require_once $basePath . '/vendor/autoload.php';

        Env::load($basePath . '/.env');
        date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Ho_Chi_Minh') ?? 'Asia/Ho_Chi_Minh');

        // Bảo đảm các thư mục lưu trữ tồn tại.
        foreach (['storage', 'storage/logs', 'storage/cache', 'storage/assets'] as $dir) {
            $path = $basePath . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0o775, true);
            }
        }

        self::$booted = true;
    }

    public static function basePath(string $relative = ''): string
    {
        $base = dirname(__DIR__, 2);

        return $relative === '' ? $base : $base . '/' . ltrim($relative, '/');
    }

    public static function storagePath(string $relative = ''): string
    {
        $base = self::basePath(Env::get('STORAGE_PATH', 'storage') ?? 'storage');

        return $relative === '' ? $base : $base . '/' . ltrim($relative, '/');
    }
}