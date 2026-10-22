<?php

declare(strict_types=1);

namespace App\Command;

use App\Support\Bootstrap;
use App\Support\Database;
use App\Support\Env;

/**
 * Kiem tra ket noi PDO toi MySQL khong ro mat khau ra log.
 */
require __DIR__ . '/../vendor/autoload.php';
Bootstrap::boot(__DIR__ . '/..');

$host = Env::get('DB_HOST');
$port = Env::int('DB_PORT', 3306);
$name = Env::get('DB_NAME');
$user = Env::get('DB_USER');
$pass = Env::get('DB_PASS');

echo "=== DeTai16 :: Kiem tra ket noi MySQL ===\n";
printf("Host      : %s:%d\n", $host, $port);
printf("Database  : %s\n", $name);
printf("User      : %s\n", $user);
printf("Password  : %s\n", ($pass === null || $pass === '') ? '(rong)' : str_repeat('*', 8) . ' (da cau hinh)');
echo str_repeat('-', 46) . "\n";

try {
    $pdo = Database::pdo();
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    $charset = $pdo->query('SELECT @@character_set_database')->fetchColumn();
    $collation = $pdo->query('SELECT @@collation_database')->fetchColumn();

    echo "[OK] Ket noi PDO thanh cong.\n";
    printf("  Server version : %s\n", $version);
    printf("  Charset        : %s\n", $charset);
    printf("  Collation      : %s\n", $collation);

    $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
    printf("  Tables         : %d%s\n", count($tables), $tables !== [] ? ' (' . implode(', ', $tables) . ')' : '');

    echo "\n[OK] Tat ca thanh cong.\n";
    exit(0);
} catch (\Throwable $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
    exit(1);
}