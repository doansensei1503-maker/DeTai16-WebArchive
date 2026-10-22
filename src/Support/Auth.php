<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Xac thuc nguoi dung bang username/email + mat khau.
 * Mat khau luon luu bang password_hash(), kiem tra bang password_verify().
 */
final class Auth
{
    private const SESSION_USER_ID = '_auth_user_id';

    private static ?\App\Domain\User $cached = null;

    /** @return \App\Domain\User|null */
    public static function attempt(string $identifier, string $password): ?\App\Domain\User
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return null;
        }

        $row = Database::first(
            'SELECT id, username, email, display_name, password_hash, is_active
               FROM users
              WHERE username = :id1 OR email = :id2
              LIMIT 1',
            [':id1' => $identifier, ':id2' => $identifier],
        );

        if ($row === null) {
            // Dung hash gia de ton tai thoi gian de chan so do bien do va thoi gian phan ung.
            password_verify($password, '$2y$10$usesomesillystringfoeeoxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

            return null;
        }

        if (!password_verify($password, (string) $row['password_hash'])) {
            return null;
        }

        if ((int) $row['is_active'] !== 1) {
            return null;
        }

        // Nang cap hash neu thuat toan da cu.
        if (password_needs_rehash((string) $row['password_hash'], PASSWORD_DEFAULT)) {
            Database::execute(
                'UPDATE users SET password_hash = :h WHERE id = :id',
                [':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => (int) $row['id']],
            );
        }

        $user = \App\Domain\User::fromRow($row);
        self::login($user);

        return $user;
    }

    public static function login(\App\Domain\User $user): void
    {
        Security::startSession();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = $user->id;
        $_SESSION['_created_at'] = time();
        self::$cached = $user;
    }

    public static function logout(): void
    {
        Security::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        self::$cached = null;
    }

    /** @return \App\Domain\User|null */
    public static function user(): ?\App\Domain\User
    {
        if (self::$cached instanceof \App\Domain\User) {
            return self::$cached;
        }

        Security::startSession();
        $id = $_SESSION[self::SESSION_USER_ID] ?? null;
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return null;
        }

        $row = Database::first(
            'SELECT id, username, email, display_name, is_active FROM users WHERE id = :id LIMIT 1',
            [':id' => (int) $id],
        );

        if ($row === null || (int) $row['is_active'] !== 1) {
            return null;
        }

        self::$cached = \App\Domain\User::fromRow($row);

        return self::$cached;
    }

    public static function id(): ?int
    {
        return self::user()?->id;
    }

    public static function check(): bool
    {
        return self::user() instanceof \App\Domain\User;
    }

    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    /** Dung cho test: reset cache. */
    public static function resetCache(): void
    {
        self::$cached = null;
    }
}