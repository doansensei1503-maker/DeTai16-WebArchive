<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\FetchStatus;
use App\Support\Database;

/**
 * QUAN LY FETCH LOG (trang thai lay noi dung - Chuc nang 1E).
 */
final class FetchLogService
{
    public static function create(
        int $libraryId,
        int $userId,
        string $url,
        ?string $finalUrl = null,
    ): int {
        Database::execute(
            'INSERT INTO fetch_logs (library_id, requested_by, requested_url, final_url, status)
             VALUES (:l, :u, :r, :f, :s)',
            [
                ':l' => $libraryId,
                ':u' => $userId,
                ':r' => $url,
                ':f' => $finalUrl,
                ':s' => FetchStatus::Pending->value,
            ],
        );

        return Database::lastId();
    }

    public static function update(
        int $logId,
        FetchStatus $status,
        ?string $finalUrl = null,
        ?int $httpCode = null,
        ?string $message = null,
        ?string $redirectChain = null,
        ?int $durationMs = null,
    ): void {
        // fetch_logs KHONG co cot updated_at (chi users / libraries / saved_pages
        // co cot ay). Thoi gian sua doi trung voi created_at cho moi lan
        // vi status chi duoc set mot lan sau khi log da duoc tao.
        $sql = 'UPDATE fetch_logs
                   SET status = :s, final_url = :f, http_code = :c, message = :m,
                       redirect_chain = :rc, duration_ms = :d
                 WHERE id = :id';
        Database::execute($sql, [
            ':id' => $logId,
            ':s'  => $status->value,
            ':f'  => $finalUrl,
            ':c'  => $httpCode,
            ':m'  => $message,
            ':rc' => $redirectChain,
            ':d'  => $durationMs,
        ]);
    }

    public static function find(int $logId): ?array
    {
        return Database::first(
            'SELECT * FROM fetch_logs WHERE id = :id LIMIT 1',
            [':id' => $logId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function recentForLibrary(int $libraryId, int $limit = 50): array
    {
        return Database::all(
            'SELECT fl.*, u.username AS requested_by_username
               FROM fetch_logs fl
               LEFT JOIN users u ON u.id = fl.requested_by
              WHERE fl.library_id = :l
              ORDER BY fl.created_at DESC
              LIMIT :lim',
            [':l' => $libraryId, ':lim' => min(100, max(1, $limit))],
        );
    }

    public static function deleteForLibrary(int $libraryId): void
    {
        Database::execute(
            'DELETE FROM fetch_logs WHERE library_id = :l',
            [':l' => $libraryId],
        );
    }
}