<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\LibraryRole;
use App\Support\Database;
use App\Support\Str;

/**
 * QUAN LY THU VIEN (Chuc nang 1A).
 * Moi thu vien doc lap, du lieu khong bi tron lan giua cac thu vien.
 */
final class LibraryService
{
    /**
     * Tao thu vien moi. Chu thu vien duoc gan role owner trong library_members.
     */
    public static function create(int $ownerId, string $name, string $description = ''): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Tên thư viện không được để trống.');
        }
        $name = Str::limit($name, 150);
        $description = Str::limit(trim($description), 2000);

        $slug = self::uniqueSlug($ownerId, $name);

        return Database::transaction(static function () use ($ownerId, $name, $slug, $description): int {
            Database::execute(
                'INSERT INTO libraries (owner_id, name, slug, description) VALUES (:o, :n, :s, :d)',
                [':o' => $ownerId, ':n' => $name, ':s' => $slug, ':d' => $description],
            );
            $libraryId = Database::lastId();

            Database::execute(
                'INSERT INTO library_members (library_id, user_id, role) VALUES (:l, :u, :r)',
                [':l' => $libraryId, ':u' => $ownerId, ':r' => LibraryRole::Owner->value],
            );

            return $libraryId;
        });
    }

    private static function uniqueSlug(int $ownerId, string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'thu-vien';
        }

        $slug = $base;
        $suffix = 1;
        while (Database::value(
            'SELECT id FROM libraries WHERE owner_id = :o AND slug = :s LIMIT 1',
            [':o' => $ownerId, ':s' => $slug],
        ) !== null) {
            $suffix++;
            $slug = $base . '-' . $suffix;
        }

        return Str::limit($slug, 160);
    }

    /** @return array<string,mixed>|null */
    public static function find(int $libraryId): ?array
    {
        return Database::first(
            'SELECT l.*, u.username AS owner_username, u.display_name AS owner_name
               FROM libraries l
               JOIN users u ON u.id = l.owner_id
              WHERE l.id = :id
              LIMIT 1',
            [':id' => $libraryId],
        );
    }

    public static function update(int $libraryId, string $name, string $description = ''): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Tên thư viện không được để trống.');
        }

        Database::execute(
            'UPDATE libraries SET name = :n, description = :d WHERE id = :id',
            [
                ':n'  => Str::limit($name, 150),
                ':d'  => Str::limit(trim($description), 2000),
                ':id' => $libraryId,
            ],
        );
    }

    public static function delete(int $libraryId): void
    {
        // Asset files are cleaned up separately by AssetStorage.
        Database::execute('DELETE FROM libraries WHERE id = :id', [':id' => $libraryId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function pagesInLibrary(int $libraryId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 200));

        $rows = Database::all(
            'SELECT sp.id, sp.title, sp.source_url, sp.saved_at, sp.status, sp.word_count,
                    sp.excerpt, sp.created_by, u.username AS created_by_username,
                    (SELECT sn.id FROM snapshots sn WHERE sn.saved_page_id = sp.id ORDER BY sn.version_no DESC LIMIT 1) AS snapshot_id,
                    (SELECT COUNT(*) FROM assets a WHERE a.library_id = sp.library_id AND a.status = "saved") AS library_assets
               FROM saved_pages sp
               JOIN users u ON u.id = sp.created_by
              WHERE sp.library_id = :l
              ORDER BY sp.saved_at DESC, sp.id DESC
              LIMIT :lim OFFSET :off',
            [':l' => $libraryId, ':lim' => $limit, ':off' => max(0, $offset)],
        );

        if ($rows === []) {
            return [];
        }

        return self::attachCardData($rows);
    }

    /**
     * Bo sung du lieu cho the bai viet: danh sach nhan + hash anh dau tien.
     *
     * Chi doc, khong doi schema. Gom 2 truy van cho CA thu vien thay vi
     * 2 truy van cho MOI bai (tranh N+1) — khoi luong du lieu cua bai tap
     * lon nho nen gom theo thu vien la du.
     *
     * @param  array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function attachCardData(array $rows): array
    {
        $pageIds = array_map(static fn (array $r): int => (int) $r['id'], $rows);

        $placeholders = implode(',', array_fill(0, count($pageIds), '?'));

        $tagRows = Database::all(
            "SELECT pt.saved_page_id, t.name, t.color
               FROM page_tags pt
               JOIN tags t ON t.id = pt.tag_id
              WHERE pt.saved_page_id IN ({$placeholders})
              ORDER BY t.name ASC",
            $pageIds,
        );

        // Anh dau tien cua ban doc lai: ordinal nho nhat trong snapshot_assets.
        // Lay theo snapshot version cao nhat cua moi bai.
        $imageRows = Database::all(
            "SELECT sn.saved_page_id, a.url_hash
               FROM snapshots sn
               JOIN snapshot_assets sa ON sa.snapshot_id = sn.id
               JOIN assets a ON a.id = sa.asset_id AND a.status = 'saved'
              WHERE sn.saved_page_id IN ({$placeholders})
                AND sn.version_no = (
                    SELECT MAX(sn2.version_no) FROM snapshots sn2
                     WHERE sn2.saved_page_id = sn.saved_page_id
                )
              ORDER BY sn.saved_page_id, sa.ordinal
              LIMIT 500",
            $pageIds,
        );

        $tagsByPage = [];
        foreach ($tagRows as $t) {
            $tagsByPage[(int) $t['saved_page_id']][] = [
                'name'  => (string) $t['name'],
                'color' => (string) $t['color'],
            ];
        }

        $imageByPage = [];
        foreach ($imageRows as $i) {
            // ORDER BY da dam bao anh ordinal nho nhat den truoc -> giu lan dau.
            $imageByPage[(int) $i['saved_page_id']] ??= (string) $i['url_hash'];
        }

        foreach ($rows as $i => $row) {
            $rows[$i]['tags'] = $tagsByPage[(int) $row['id']] ?? [];
            $rows[$i]['image_hash'] = $imageByPage[(int) $row['id']] ?? null;
        }

        return $rows;
    }

    public static function countPages(int $libraryId): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM saved_pages WHERE library_id = :l',
            [':l' => $libraryId],
        );
    }

    /** @return array<string,int> */
    public static function libraryStats(int $libraryId): array
    {
        $row = Database::first(
            'SELECT
                (SELECT COUNT(*) FROM saved_pages WHERE library_id = :l1 AND status = "saved") AS saved_pages,
                (SELECT COUNT(*) FROM assets a WHERE a.library_id = :l2 AND a.status = "saved") AS saved_images,
                (SELECT COUNT(*) FROM assets a WHERE a.library_id = :l3 AND a.status = "failed") AS failed_images,
                (SELECT COUNT(*) FROM tags WHERE library_id = :l4) AS tags,
                (SELECT COUNT(*) FROM library_members WHERE library_id = :l5) AS members,
                (SELECT COALESCE(SUM(sp.word_count), 0) FROM saved_pages sp WHERE sp.library_id = :l6 AND sp.status = "saved") AS words,
                (SELECT COALESCE(SUM(a.byte_size), 0) FROM assets a WHERE a.library_id = :l7 AND a.status = "saved") AS image_bytes',
            [
                ':l1' => $libraryId, ':l2' => $libraryId, ':l3' => $libraryId,
                ':l4' => $libraryId, ':l5' => $libraryId, ':l6' => $libraryId, ':l7' => $libraryId,
            ],
        ) ?? [];

        return array_map('intval', $row);
    }
}