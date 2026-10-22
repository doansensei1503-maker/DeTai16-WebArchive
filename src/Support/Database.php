<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lop truy cap du lieu duy nhat. Dung PDO + prepared statements.
 */
final class Database
{
    private static ?\PDO $pdo = null;

    public static function pdo(): \PDO
    {
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }

        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::int('DB_PORT', 3306);
        $name = Env::get('DB_NAME', 'detai16');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '') ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        self::$pdo = new \PDO($dsn, (string) $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);

        return self::$pdo;
    }

    /** Dung khi can ket noi MySQL ma khong can chon database (migrate/seed). */
    public static function serverPdo(): \PDO
    {
        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::int('DB_PORT', 3306);
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        return new \PDO(
            "mysql:host={$host};port={$port};charset={$charset}",
            (string) Env::get('DB_USER', 'root'),
            Env::get('DB_PASS', '') ?? '',
            [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    /** @param array<string|int,mixed> $params */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        self::assertPlaceholdersSatisfied($sql, $params);

        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * MySQL voi ATTR_EMULATE_PREPARES = false KHONG cho phep dung lai mot
     * placeholder co ten o nhieu vi tri (vi du ON DUPLICATE KEY UPDATE).
     * Loi 'Invalid parameter number' khi do rat kho truy nguyen — ta bat
     * loi nay ngay tai day bang thong diep ro rang.
     *
     * @param array<string|int,mixed> $params
     */
    private static function assertPlaceholdersSatisfied(string $sql, array $params): void
    {
        if ($params === []) {
            return;
        }

        // Dem so lan xuat hien cua tung placeholder co ten trong SQL.
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);
        $counts = array_count_values($matches[1]);

        $unused = [];
        foreach (array_keys($params) as $key) {
            if (!is_string($key) || !str_starts_with($key, ':')) {
                continue;
            }
            if (!isset($counts[substr($key, 1)])) {
                $unused[] = $key;
            }
        }

        if ($unused !== []) {
            throw new \InvalidArgumentException(
                'Tham so khong duoc dung trong cau lenh SQL: ' . implode(', ', $unused)
                . ' | SQL: ' . preg_replace('/\s+/', ' ', $sql)
            );
        }
    }

    /**
     * @param  array<string|int,mixed> $params
     * @return array<string,mixed>|null
     */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param  array<string|int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @param array<string|int,mixed> $params */
    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string|int,mixed> $params */
    public static function execute(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    public static function lastId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}