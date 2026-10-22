<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\FetchStatus;
use App\Support\Database;
use App\Support\Str;

/**
 * LUU BAI VIET VAO MYSQL (Chuc nang 2D).
 *
 * Dung khoa duy nhat (library_id + source_url_hash) de tranh ghep nham
 * hai bai cung tieu de nhung noi dung khac nhau.
 */
final class ArticleService
{
    public static function save(
        int $libraryId,
        int $userId,
        string $sourceUrl,
        string $title,
        string $author = '',
        string $contentHtml = '',
        string $contentText = '',
        string $excerpt = '',
        ?string $publishedAt = null,
        string $language = 'vi',
        ?string $layoutUsed = null,
        ?int $fetchLogId = null,
    ): int {
        $normalized = Str::normalizeUrl($sourceUrl);
        $urlHash = Str::sha256($normalized);
        $contentHash = Str::sha256($contentHtml);
        $wordCount = self::countWords($contentText);

        // Kiem tra trung URL trong cung thu vien.
        $existing = Database::first(
            'SELECT id FROM saved_pages WHERE library_id = :l AND source_url_hash = :h LIMIT 1',
            [':l' => $libraryId, ':h' => $urlHash],
        );

        if ($existing !== null) {
            throw new \InvalidArgumentException(
                'URL này đã được lưu trong thư viện. Bạn có thể xem bản đọc lại đã tồn tại.',
            );
        }

        Database::execute(
            'INSERT INTO saved_pages
                (library_id, created_by, source_url, source_url_hash, title, author,
                 published_at, excerpt, content_html, content_text, word_count,
                 language, status, content_hash, layout_used, fetch_log_id, saved_at)
             VALUES
                (:lib, :user, :url, :uhash, :title, :author, :pub, :excerpt, :html, :text,
                 :wc, :lang, :status, :chash, :layout, :flog, NOW())',
            [
                ':lib'     => $libraryId,
                ':user'    => $userId,
                ':url'     => $normalized,
                ':uhash'   => $urlHash,
                ':title'   => Str::limit($title, 500),
                ':author'  => Str::limit($author, 190),
                ':pub'     => $publishedAt,
                ':excerpt' => $excerpt !== '' ? $excerpt : Str::excerpt($contentText, 200),
                ':html'    => $contentHtml,
                ':text'    => $contentText,
                ':wc'      => $wordCount,
                ':lang'    => $language,
                ':status'  => FetchStatus::Saved->value,
                ':chash'   => $contentHash,
                ':layout'  => $layoutUsed,
                ':flog'    => $fetchLogId,
            ],
        );

        return Database::lastId();
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT * FROM saved_pages WHERE id = :id LIMIT 1',
            [':id' => $id],
        );
    }

    /** @return array<string,mixed>|null */
    public static function findByUrl(int $libraryId, string $url): ?array
    {
        return Database::first(
            'SELECT * FROM saved_pages WHERE library_id = :l AND source_url_hash = :h LIMIT 1',
            [':l' => $libraryId, ':h' => Str::sha256(Str::normalizeUrl($url))],
        );
    }

    /** Cap nhat trang thai (dung khi can danh dau that bai). */
    public static function updateStatus(int $id, FetchStatus $status, string $message = ''): void
    {
        Database::execute(
            'UPDATE saved_pages SET status = :s, updated_at = NOW() WHERE id = :id',
            [':s' => $status->value, ':id' => $id],
        );
    }

    private static function countWords(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        return count(array_filter($words, static fn (string $w): bool => $w !== ''));
    }
}