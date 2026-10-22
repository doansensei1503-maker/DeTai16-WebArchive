<?php

declare(strict_types=1);

/**
 * DeTai16 Console Tool
 *
 * Usage:
 *   php bin/console.php migrate
 *   php bin/console.php seed
 *   php bin/console.php fresh
 *   php bin/console.php help
 */

// KHONG require file .env o day! .env KHONG phai file PHP — require se in
// toan bo noi dung cua no ra man hinh (leak mat khau).
// File .env duoc Bootstrap::boot() doc bang Env::load() mot cach an toan.

require __DIR__ . '/../vendor/autoload.php';

\App\Support\Bootstrap::boot(__DIR__ . '/..');

use App\Domain\LibraryRole;
use App\Support\Database;
use App\Support\Env;

$cmd = $argv[1] ?? 'help';

function info(string $msg): void { echo "ℹ  {$msg}\n"; }
function ok(string $msg): void   { echo "✅ {$msg}\n"; }
function warn(string $msg): void { echo "⚠  {$msg}\n"; }
function error(string $msg): void { echo "❌ {$msg}\n"; }

switch ($cmd) {
    case 'migrate':
        migrate();
        break;

    case 'seed':
        seed();
        break;

    case 'fresh':
        fresh();
        break;

    case 'help':
    default:
        help();
        break;
}

function migrate(): int
{
    info('=== DeTai16 Migration ===');

    $sqlFile = __DIR__ . '/../database/001_schema.sql';
    if (!is_file($sqlFile)) {
        error("Schema file not found: {$sqlFile}");
        return 1;
    }

    try {
        $pdo = Database::serverPdo();

        $dbName = Env::get('DB_NAME', 'detai16');
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        ok("Database '{$dbName}' ready.");
        $pdo->exec("USE `{$dbName}`");

        // Dung bo tach cau lenh state-machine (khong phai regex).
        $result = \App\Support\SqlScriptRunner::runFile($sqlFile, $pdo);

        if ($result['failed'] > 0) {
            foreach ($result['errors'] as $err) {
                error('  ' . $err);
            }
            $okCount = $result['ok'];
            $failCount = $result['failed'];
            error("Migration that bai: {$okCount} thanh cong, {$failCount} that bai.");
            return 1;
        }

        $stmtCount = $result['ok'];
        ok("Schema applied successfully ({$stmtCount} statements).");

        return 0;
    } catch (\Throwable $e) {
        error('Migration failed: ' . $e->getMessage());
        return 1;
    }
}

function seed(): int
{
    info('=== DeTai16 Seed ===');

    try {
        $pdo = Database::pdo();

        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            warn("Database already has {$count} user(s). Skipping seed.");
            info("Run 'php bin/console.php fresh' to reset.");
            return 0;
        }

        $users = [
            ['admin',   'admin@detai16.local',   'Admin DeTai16',   'admin1234'],
            ['thanhvien','tv@detai16.local',    'Thành viên 1',    'tv123456'],
            ['khanhnt',  'khanh@detai16.local', 'Nguyen Tran Khanh','khanh123'],
            ['hieupt',   'hieu@detai16.local',   'Pham Tuan Hieu',  'hieu123'],
            ['namnh',    'nam@detai16.local',    'Ngo Ngoc Nam',    'nam123'],
        ];

        foreach ($users as [$username, $email, $displayName, $password]) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare(
                'INSERT INTO users (username, email, display_name, password_hash) VALUES (:u, :e, :d, :h)',
            )->execute([':u' => $username, ':e' => $email, ':d' => $displayName, ':h' => $hash]);

            $userId = (int) $pdo->lastInsertId();
            ok("  User '{$username}' (id={$userId}, pass='{$password}')");
        }

        // Tao thu vien mau.
        $libData = [
            [1, 'CNTT & Lap Trinh',      'cntt-lap-trinh',      'Tong hop bai viet ve lap trinh, thuat toan, framework.'],
            [1, 'Khoa Hoc & Cong Nghe',  'khoa-hoc-cong-nghe',  'Cac bai viet khoa hoc, cong nghe, AI, Blockchain.'],
            [2, 'Thu Vien Rieng',         'thu-vien-rieng',      'Thu vien ca nhan cua thanh vien.'],
        ];

        foreach ($libData as [$ownerId, $name, $slug, $desc]) {
            $pdo->prepare(
                'INSERT INTO libraries (owner_id, name, slug, description) VALUES (:o, :n, :s, :d)',
            )->execute([':o' => $ownerId, ':n' => $name, ':s' => $slug, ':d' => $desc]);
            $libId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO library_members (library_id, user_id, role) VALUES (:l, :u, :r)',
            )->execute([':l' => $libId, ':u' => $ownerId, ':r' => LibraryRole::Owner->value]);

            ok("  Library '{$name}' (id={$libId})");
        }

        // Them thanh vien vao thu vien CNTT (id=1).
        $pdo->prepare('INSERT INTO library_members (library_id, user_id, role) VALUES (:l, :u, :r)')
            ->execute([':l' => 1, ':u' => 2, ':r' => LibraryRole::Editor->value]);
        $pdo->prepare('INSERT INTO library_members (library_id, user_id, role) VALUES (:l, :u, :r)')
            ->execute([':l' => 1, ':u' => 3, ':r' => LibraryRole::Viewer->value]);
        ok('  Members added to CNTT library.');

        // Tao nhan mau.
        $tags = [
            [1, 'PHP',         '#7b68ee'],
            [1, 'JavaScript',  '#f0db4f'],
            [1, 'Web',        '#3b6ea5'],
            [1, 'CoSoDuLieu', '#2e8b57'],
            [2, 'AI',         '#9b59b6'],
            [2, 'Blockchain', '#e67e22'],
        ];

        foreach ($tags as [$libId, $name, $color]) {
            $pdo->prepare('INSERT INTO tags (library_id, name, color) VALUES (:l, :n, :c)')
                ->execute([':l' => $libId, ':n' => $name, ':c' => $color]);
        }
        ok('  Sample tags created.');

        ok('Seed completed.');
        return 0;
    } catch (\Throwable $e) {
        error('Seed failed: ' . $e->getMessage());
        return 1;
    }
}

function fresh(): int
{
    info('=== DeTai16 Fresh (drop + migrate + seed) ===');

    try {
        $pdo = Database::serverPdo();
        $dbName = Env::get('DB_NAME', 'detai16');
        $pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
        ok("Dropped database '{$dbName}'.");

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");

        $sqlFile = __DIR__ . '/../database/001_schema.sql';
        $result = \App\Support\SqlScriptRunner::runFile($sqlFile, $pdo);
        if ($result['failed'] > 0) {
            foreach ($result['errors'] as $err) {
                error('  ' . $err);
            }
            error("Fresh that bai: {$result['failed']} cau lenh that bai.");
            return 1;
        }
        ok('Schema applied.');

        seed();
        ok('Fresh completed successfully.');
        return 0;
    } catch (\Throwable $e) {
        error('Fresh failed: ' . $e->getMessage());
        return 1;
    }
}

function help(): void
{
    echo <<<'HELP'
DeTai16 Console Tool

Usage:
  php bin/console.php <command>

Commands:
  migrate  Apply database schema
  seed     Seed sample data (admin / admin1234)
  fresh    Drop database + migrate + seed
  help     Show this help

HELP;
}
