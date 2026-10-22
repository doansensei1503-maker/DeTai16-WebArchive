<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Lop co so cho test can database.
 * Cac test se bi skip neu khong ket noi duoc MySQL.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static bool $dbAvailable = false;
    protected static string $dbUnavailableReason = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        try {
            \App\Support\Database::pdo()->query('SELECT 1');
            self::$dbAvailable = true;
        } catch (\Throwable $e) {
            self::$dbAvailable = false;
            self::$dbUnavailableReason = 'Database khong san sang: ' . $e->getMessage();
        }
    }

    protected function requireDatabase(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped(self::$dbUnavailableReason);
        }
    }

    /** @return array<string,mixed> */
    protected function createUser(string $username, string $password = 'test1234'): int
    {
        $existing = \App\Support\Database::value(
            'SELECT id FROM users WHERE username = :u LIMIT 1',
            [':u' => $username],
        );

        if ($existing !== null) {
            return (int) $existing;
        }

        \App\Service\UserService::register($username, $username . '@test.local', $password, $username);

        return (int) \App\Support\Database::value(
            'SELECT id FROM users WHERE username = :u LIMIT 1',
            [':u' => $username],
        );
    }
}