<?php

declare(strict_types=1);

namespace App\Service;

use App\Security\HtmlSanitizer;
use App\Support\Database;
use App\Support\Env;
use App\Support\Str;

/**
 * TAO BAN DOC LAI (Chuc nang 3A).
 *
 * Luu HTML da lam sach vao DB -> doc lap hoan toan voi trang nguon.
 * Khong tai tai nguyen tu website goc khi doc lai.
 */
final class SnapshotService
{
    /**
     * Tao ban doc lai tu bai da luu:
     *   1. Tim anh trong noi dung
     *   2. Luu anh cuc bo
     *   3. Lam sach HTML + thay the duong dan anh
     *   4. Luu vao bang snapshots
     */
    public static function create(int $savedPageId, int $libraryId): int
    {
        $page = Database::first(
            'SELECT * FROM saved_pages WHERE id = :id LIMIT 1',
            [':id' => $savedPageId],
        );

        if ($page === null) {
            throw new \InvalidArgumentException('Bài viết không tồn tại.');
        }

        $contentHtml = (string) ($page['content_html'] ?? '');
        if (trim($contentHtml) === '') {
            throw new \InvalidArgumentException('Bài viết không có nội dung HTML để tạo bản đọc lại.');
        }

        // 1. Tim va luu anh.
        $imageMap = self::saveImages($contentHtml, $libraryId, (string) $page['source_url']);

        // 2. Lam sach HTML, thay the tham chieu anh.
        $sanitizer = new HtmlSanitizer();
        $cleanHtml = $sanitizer->sanitize($contentHtml, $imageMap);

        // 3. Kiem tra ket qua.
        $plainText = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($cleanHtml), ENT_QUOTES, 'UTF-8')) ?? '');
        if ($plainText === '') {
            throw new \InvalidArgumentException('Bản đọc lại rỗng sau khi làm sạch HTML.');
        }

        $imageCount = count($imageMap);
        $missingCount = self::countMissingImages($contentHtml, $imageMap);

        // Version tiep theo.
        $nextVersion = (int) Database::value(
            'SELECT COALESCE(MAX(version_no), 0) + 1 FROM snapshots WHERE saved_page_id = :p',
            [':p' => $savedPageId],
        );

        $csp = self::buildCsp();

        Database::execute(
            'INSERT INTO snapshots
                (saved_page_id, version_no, html, text_length, html_hash,
                 image_count, missing_image_count, csp)
             VALUES
                (:p, :v, :html, :tl, :hash, :ic, :mc, :csp)',
            [
                ':p'    => $savedPageId,
                ':v'    => $nextVersion,
                ':html' => $cleanHtml,
                ':tl'   => mb_strlen($plainText, 'UTF-8'),
                ':hash' => Str::sha256($cleanHtml),
                ':ic'   => $imageCount,
                ':mc'   => $missingCount,
                ':csp'  => $csp,
            ],
        );

        $snapshotId = Database::lastId();

        // 4. Lien ket snapshot <-> asset.
        foreach ($imageMap as $originalUrl => $internalPath) {
            $assetId = Database::value(
                'SELECT id FROM assets WHERE library_id = :l AND original_url = :u LIMIT 1',
                [':l' => $libraryId, ':u' => $originalUrl],
            );

            if ($assetId !== null) {
                Database::execute(
                    'INSERT IGNORE INTO snapshot_assets (snapshot_id, asset_id, ordinal) VALUES (:s, :a, :o)',
                    [':s' => $snapshotId, ':a' => (int) $assetId, ':o' => 0],
                );
            }
        }

        return $snapshotId;
    }

    /**
     * Tim anh trong HTML va luu cuc bo.
     *
     * @return array<string,string> Map [originalUrl => internalPath]
     */
    private static function saveImages(string $html, int $libraryId, string $pageUrl): array
    {
        $imageMap = [];

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $images = $xpath->query('//img[@src]');

        if ($images === false) {
            return $imageMap;
        }

        $storage = new AssetStorage();

        foreach ($images as $img) {
            if (!$img instanceof \DOMElement) {
                continue;
            }

            $src = trim($img->getAttribute('src'));
            if ($src === '') {
                continue;
            }

            // Chuyen duong dan tuong doi thanh tuyet doi.
            $absolute = self::absoluteUrl($src, $pageUrl);
            if ($absolute === null) {
                continue;
            }

            $result = $storage->saveFromUrl($libraryId, $absolute);

            if ($result['success'] && $result['internal_path'] !== null) {
                $imageMap[$absolute] = $result['internal_path'];
                // Giu ca ban goc de khop khi sanitizer chay.
                $imageMap[$src] = $result['internal_path'];
            }
        }

        return $imageMap;
    }

    /** Chuyen duong dan tuong doi / tuy doi thanh URL tuyet doi. */
    private static function absoluteUrl(string $src, string $baseUrl): ?string
    {
        if (preg_match('#^https?://#i', $src) === 1) {
            return $src;
        }

        if (str_starts_with($src, 'data:')) {
            return null; // Khong tai, bo qua data URI
        }

        $parts = parse_url($baseUrl);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';

        if (str_starts_with($src, '//')) {
            return "{$scheme}:{$src}";
        }

        if (str_starts_with($src, '/')) {
            return "{$scheme}://{$host}{$port}{$src}";
        }

        if (str_starts_with($src, '#')) {
            return $baseUrl;
        }

        // Duong dan tuong doi.
        $dir = rtrim(dirname($path), '/');
        return "{$scheme}://{$host}{$port}{$dir}/{$src}";
    }

    private static function countMissingImages(string $html, array $imageMap): int
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $images = $xpath->query('//img[@src]');
        if ($images === false) {
            return 0;
        }

        $missing = 0;
        foreach ($images as $img) {
            $src = trim($img->getAttribute('src'));
            if ($src === '') {
                $missing++;
                continue;
            }

            if (!isset($imageMap[$src])) {
                $missing++;
            }
        }

        return $missing;
    }

    /**
     * Content Security Policy cho trang doc lai:
     *   - Khong cho chay script
     *   - Chi cho tai anh tu cung noi bo
     */
    private static function buildCsp(): string
    {
        return "default-src 'none'; "
            . "img-src 'self'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "script-src 'none'; "
            . "frame-ancestors 'none'; "
            . "base-uri 'none'; "
            . "form-action 'none'";
    }

    /** @return array<string,mixed>|null */
    public static function find(int $snapshotId): ?array
    {
        return Database::first(
            'SELECT * FROM snapshots WHERE id = :id LIMIT 1',
            [':id' => $snapshotId],
        );
    }

    /** @return array<string,mixed>|null */
    public static function latestForPage(int $savedPageId): ?array
    {
        return Database::first(
            'SELECT * FROM snapshots WHERE saved_page_id = :p ORDER BY version_no DESC LIMIT 1',
            [':p' => $savedPageId],
        );
    }

    public static function applyCspHeader(string $csp): void
    {
        header('Content-Security-Policy: ' . $csp);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
    }
}