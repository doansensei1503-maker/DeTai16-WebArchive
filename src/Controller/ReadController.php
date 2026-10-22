<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AccessControl;
use App\Service\SnapshotService;
use App\Support\Database;

/**
 * Trang doc lai: hien thi ban HTML da lam sach (CN3).
 * Tat ca kiem tra quyen tai day.
 */
final class ReadController extends BaseController
{
    public function show(int $snapshotId): void
    {
        $this->requireAuth();

        $snapshot = Database::first(
            'SELECT sn.*, sp.library_id, sp.source_url, sp.title, sp.author,
                    sp.published_at, sp.saved_at, sp.word_count, sp.status AS page_status
               FROM snapshots sn
               JOIN saved_pages sp ON sp.id = sn.saved_page_id
              WHERE sn.id = :id LIMIT 1',
            [':id' => $snapshotId],
        );

        if ($snapshot === null) {
            $this->backWithErrors(['general' => 'Bản đọc lại không tồn tại.']);
        }

        $libraryId = (int) $snapshot['library_id'];
        if (!AccessControl::canRead($libraryId)) {
            $this->backWithErrors(['general' => 'Bạn không có quyền xem bản đọc lại này.']);
        }

        // saved_pages.status la VARCHAR (schema 001_schema.sql): 'saved'.
        // Khong phai bien so — truoc day so sanh voi 1 lam trang doc lai
        // luon bao loi.
        if ((string) $snapshot['page_status'] !== 'saved') {
            $this->backWithErrors(['general' => 'Bài viết không còn khả dụng.']);
        }

        $library = Database::first(
            'SELECT name FROM libraries WHERE id = :id',
            [':id' => $libraryId],
        );

        $title = $snapshot['title'] ?: 'Bài viết không có tiêu đề';

        // Trang doc lai phai chay duoc offline va tuyet doi khong duoc
        // tai tai nguyen ben ngoai. CSP da luu cung voi snapshot nen
        // phai duoc gan LAI o day — thieu header nay thi trang doc lai
        // khong bi khoa, va noi dung da lam sach co the tai ngoai du.
        $csp = trim((string) ($snapshot['csp'] ?? ''));
        if ($csp === '') {
            // Snapshot cu khong luu CSP -> dung policy chat nhat.
            $csp = "default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; "
                . "script-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'";
        }
        SnapshotService::applyCspHeader($csp);

        $this->render('read/show.php', [
            'title'     => (string) $title,
            'snapshot'  => $snapshot,
            'library'   => $library,
            'libraryId' => $libraryId,
        ]);
    }
}