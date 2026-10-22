<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\Auth;
use App\Support\Database;

/**
 * QUAN LY NGUOI DUNG.
 */
final class UserService
{
    public static function register(
        string $username,
        string $email,
        string $password,
        string $displayName = '',
    ): int {
        $username = trim($username);
        $email = trim($email);

        self::validateUsername($username);
        self::validateEmail($email);
        self::validatePassword($password);

        $displayName = trim($displayName);
        if ($displayName === '') {
            $displayName = $username;
        }

        $hash = Auth::hash($password);

        Database::execute(
            'INSERT INTO users (username, email, display_name, password_hash) VALUES (:u, :e, :d, :h)',
            [':u' => $username, ':e' => $email, ':d' => $displayName, ':h' => $hash],
        );

        return Database::lastId();
    }

    /** @return array<string,mixed>|null */
    public static function findByUsername(string $username): ?array
    {
        return Database::first(
            'SELECT id, username, email, display_name, is_active FROM users WHERE username = :u LIMIT 1',
            [':u' => trim($username)],
        );
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        return Database::first(
            'SELECT id, username, email, display_name, is_active, created_at FROM users WHERE id = :id LIMIT 1',
            [':id' => $id],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function searchByUsername(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        return Database::all(
            'SELECT id, username, email, display_name FROM users
              WHERE is_active = 1 AND username LIKE :q
              ORDER BY username ASC
              LIMIT :lim',
            [':q' => $query . '%', ':lim' => min(20, max(1, $limit))],
        );
    }

    public static function updateProfile(int $userId, string $displayName, string $email): void
    {
        $email = trim($email);
        self::validateEmail($email);

        Database::execute(
            'UPDATE users SET display_name = :d, email = :e WHERE id = :id',
            [':d' => trim($displayName), ':e' => $email, ':id' => $userId],
        );
    }

    public static function updatePassword(int $userId, string $newPassword): void
    {
        self::validatePassword($newPassword);
        Database::execute(
            'UPDATE users SET password_hash = :h WHERE id = :id',
            [':h' => Auth::hash($newPassword), ':id' => $userId],
        );
    }

    private static function validateUsername(string $username): void
    {
        $username = trim($username);
        if ($username === '') {
            throw new \InvalidArgumentException('Tên đăng nhập không được để trống.');
        }
        if (strlen($username) < 3 || strlen($username) > 64) {
            throw new \InvalidArgumentException('Tên đăng nhập phải từ 3 đến 64 ký tự.');
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            throw new \InvalidArgumentException('Tên đăng nhập chỉ được chứa chữ, số, dấu gạch dưới, dấu chấm và dấu gạch ngang.');
        }

        $existing = Database::value(
            'SELECT id FROM users WHERE username = :u LIMIT 1',
            [':u' => $username],
        );
        if ($existing !== null) {
            throw new \InvalidArgumentException("Tên đăng nhập \"{$username}\" đã được sử dụng.");
        }
    }

    private static function validateEmail(string $email): void
    {
        $email = trim($email);
        if ($email === '') {
            throw new \InvalidArgumentException('Email không được để trống.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Địa chỉ email không hợp lệ.');
        }

        $existing = Database::value(
            'SELECT id FROM users WHERE email = :e LIMIT 1',
            [':e' => $email],
        );
        if ($existing !== null) {
            throw new \InvalidArgumentException("Email \"{$email}\" đã được sử dụng.");
        }
    }

    private static function validatePassword(string $password): void
    {
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Mật khẩu phải có ít nhất 8 ký tự.');
        }
        if (strlen($password) > 128) {
            throw new \InvalidArgumentException('Mật khẩu quá dài.');
        }
    }
}