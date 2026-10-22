<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CSRF token theo session + chong khai thac clickjacking bang header.
 */
final class Security
{
    private const SESSION_KEY = '_csrf_token';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // CLI (migration, seed, kiem thu tich hop): khong tao session.
        // Neu bat session o CLI, header da duoc gui se sinh warning.
        if (PHP_SAPI === 'cli') {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }

            return;
        }

        $name = Env::get('SESSION_NAME', 'detai16_session') ?? 'detai16_session';
        $lifetime = Env::int('SESSION_LIFETIME', 7200);
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';

        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        session_start();

        // Regenerate id định kỳ để hạn chế session fixation.
        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = time();
        } elseif (time() - (int) $_SESSION['_created_at'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created_at'] = time();
        }
    }

    public static function token(): string
    {
        self::startSession();

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function verifyToken(?string $token): bool
    {
        self::startSession();
        $expected = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public static function headers(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}