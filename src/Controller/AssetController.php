<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AccessControl;
use App\Support\Database;

/**
 * Phuc vu anh da luu cuc bo voi kiem tra quyen (CN3).
 * Khong tra ve dia chi luu tru noi bo, chi tra ve file.
 */
final class AssetController extends BaseController
{
    public function serve(string $hash): void
    {
        // Lay asset qua hash (su dung snapshots de lay saved_page_id, dam bao
        // snapshot ton tai -> assets duoc lien ket qua snapshot, moi kiem duoc).
        $asset = Database::first(
            'SELECT a.*, sp.library_id
               FROM assets a
               JOIN snapshots s ON s.id IN (
                   SELECT sa.snapshot_id FROM snapshot_assets sa
                   WHERE sa.asset_id = a.id
               )
               JOIN saved_pages sp ON sp.id = s.saved_page_id
              WHERE a.url_hash = :h AND a.status = "saved"
              LIMIT 1',
            [':h' => $hash],
        );

        if ($asset === null) {
            http_response_code(404);
            header('Content-Type: image/png');
            echo $this->placeholderPng();
            exit;
        }

        $libraryId = (int) $asset['library_id'];

        // Kiem tra quyen truy cap.
        $user = \App\Support\Auth::user();
        if ($user === null || !AccessControl::canRead($libraryId, $user->id)) {
            http_response_code(403);
            exit;
        }

        $localPath = (string) $asset['local_path'];
        if ($localPath === '' || !is_file($localPath)) {
            http_response_code(404);
            header('Content-Type: image/png');
            echo $this->placeholderPng();
            exit;
        }

        // Doc mime tu DB, fallback sang file.
        $mime = (string) ($asset['mime_type'] ?: mime_content_type($localPath) ?: 'application/octet-stream');

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($localPath));
        header('Cache-Control: public, max-age=31536000, immutable');
        header('X-Content-Type-Options: nosniff');

        readfile($localPath);
        exit;
    }

    /** Tra ve 1x1 transparent PNG placeholder. */
    private function placeholderPng(): string
    {
        // 1x1 PNG (base64)
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
    }
}