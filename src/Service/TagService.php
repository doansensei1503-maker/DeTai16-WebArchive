<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\Database;
use App\Support\Str;

/**
 * QUAN LY NHAN (Chuc nang 1B).
 * Moi nhan thuoc mot thu vien -> khong tran nhan giua cac thu vien.
 */
final class TagService
{
    public static function create(int $libraryId, string $name, string $color = '#3b6ea5'): int
    {
        $name = self::normalizeName($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Tên nhãn không được để trống.');
        }

        $color = self::normalizeColor($color);

        // Trung ten nhan trong cung thu vien -> tra ve nhan cu.
        $existing = Database::value(
            'SELECT id FROM tags WHERE library_id = :l AND name = :n LIMIT 1',
            [':l' => $libraryId, ':n' => $name],
        );
        if ($existing !== null) {
            return (int) $existing;
        }

        Database::execute(
            'INSERT INTO tags (library_id, name, color) VALUES (:l, :n, :c)',
            [':l' => $libraryId, ':n' => $name, ':c' => $color],
        );

        return Database::lastId();
    }

    public static function rename(int $tagId, int $libraryId, string $name, ?string $color = null): void
    {
        $name = self::normalizeName($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Tên nhãn không được để trống.');
        }

        $clash = Database::value(
            'SELECT id FROM tags WHERE library_id = :l AND name = :n AND id <> :id LIMIT 1',
            [':l' => $libraryId, ':n' => $name, ':id' => $tagId],
        );
        if ($clash !== null) {
            throw new \InvalidArgumentException("Nhãn \"{$name}\" đã tồn tại trong thư viện này.");
        }

        $params = [':n' => $name, ':id' => $tagId, ':l' => $libraryId];
        $sql = 'UPDATE tags SET name = :n WHERE id = :id AND library_id = :l';
        if ($color !== null) {
            $sql .= ', color = :c';
            $params[':c'] = self::normalizeColor($color);
        }

        Database::execute($sql, $params);
    }

    public static function delete(int $tagId, int $libraryId): void
    {
        Database::execute(
            'DELETE FROM tags WHERE id = :id AND library_id = :l',
            [':id' => $tagId, ':l' => $libraryId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function listForLibrary(int $libraryId): array
    {
        return Database::all(
            'SELECT t.id, t.name, t.color,
                    (SELECT COUNT(*) FROM page_tags pt WHERE pt.tag_id = t.id) AS usage_count
               FROM tags t
              WHERE t.library_id = :l
              ORDER BY t.name ASC',
            [':l' => $libraryId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function tagsOfPage(int $savedPageId): array
    {
        return Database::all(
            'SELECT t.id, t.name, t.color
               FROM tags t
               JOIN page_tags pt ON pt.tag_id = t.id
              WHERE pt.saved_page_id = :p
              ORDER BY t.name ASC',
            [':p' => $savedPageId],
        );
    }

    /** Gan nhan cho mot bai da luu. Chi gan nhan thuoc cung thu vien. */
    public static function attach(int $savedPageId, int $tagId, int $libraryId): void
    {
        $valid = Database::value(
            'SELECT sp.id FROM saved_pages sp WHERE sp.id = :p AND sp.library_id = :l',
            [':p' => $savedPageId, ':l' => $libraryId],
        );
        if ($valid === null) {
            throw new \InvalidArgumentException('Bài viết không tồn tại trong thư viện này.');
        }

        $tagValid = Database::value(
            'SELECT id FROM tags WHERE id = :t AND library_id = :l',
            [':t' => $tagId, ':l' => $libraryId],
        );
        if ($tagValid === null) {
            throw new \InvalidArgumentException('Nhãn không tồn tại trong thư viện này.');
        }

        Database::execute(
            'INSERT IGNORE INTO page_tags (saved_page_id, tag_id) VALUES (:p, :t)',
            [':p' => $savedPageId, ':t' => $tagId],
        );
    }

    public static function detach(int $savedPageId, int $tagId): void
    {
        Database::execute(
            'DELETE FROM page_tags WHERE saved_page_id = :p AND tag_id = :t',
            [':p' => $savedPageId, ':t' => $tagId],
        );
    }

    /** @param array<int,int> $tagIds */
    public static function replaceForPage(int $savedPageId, array $tagIds, int $libraryId): void
    {
        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));

        Database::transaction(static function () use ($savedPageId, $tagIds, $libraryId): void {
            Database::execute('DELETE FROM page_tags WHERE saved_page_id = :p', [':p' => $savedPageId]);
            foreach ($tagIds as $tagId) {
                TagService::attach($savedPageId, $tagId, $libraryId);
            }
        });
    }

    private static function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return Str::limit($name, 80);
    }

    private static function normalizeColor(string $color): string
    {
        $color = trim($color);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : '#3b6ea5';
    }
}