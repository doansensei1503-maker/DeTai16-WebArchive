<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\LibraryRole;
use App\Support\Auth;
use App\Support\Database;

/**
 * PHAN QUYEN (Chuc nang 1C).
 *
 * Moi thao tac deu phai goi qua day. Khong tin vao duong dan URL hay
 * tham so client. Ket qua tra ve role cua user trong thu vien do.
 */
final class AccessControl
{
    /** @return array<string,mixed>|null */
    public static function libraryForUser(int $libraryId, ?int $userId = null): ?array
    {
        $userId ??= Auth::id();
        if ($userId === null) {
            return null;
        }

        return Database::first(
            'SELECT l.id, l.name, l.slug, l.description, l.owner_id, lm.role
               FROM libraries l
               JOIN library_members lm ON lm.library_id = l.id AND lm.user_id = :uid
              WHERE l.id = :lid
              LIMIT 1',
            [':uid' => $userId, ':lid' => $libraryId],
        );
    }

    public static function role(int $libraryId, ?int $userId = null): ?LibraryRole
    {
        $row = self::libraryForUser($libraryId, $userId);
        if ($row === null) {
            return null;
        }

        return LibraryRole::tryFrom((string) $row['role']);
    }

    /** Co quyen doc noi dung thu vien? */
    public static function canRead(int $libraryId, ?int $userId = null): bool
    {
        return self::role($libraryId, $userId)?->canRead() ?? false;
    }

    /** Co quyen luu bai / gan nhan? */
    public static function canWrite(int $libraryId, ?int $userId = null): bool
    {
        return self::role($libraryId, $userId)?->canWrite() ?? false;
    }

    /** Co quyen quan ly thu vien, thanh vien? */
    public static function canManage(int $libraryId, ?int $userId = null): bool
    {
        return self::role($libraryId, $userId)?->canManageLibrary() ?? false;
    }

    /**
     * Danh sach thu vien user co the truy cap, kem role.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function librariesForUser(?int $userId = null): array
    {
        $userId ??= Auth::id();
        if ($userId === null) {
            return [];
        }

        return Database::all(
            'SELECT l.id, l.name, l.slug, l.description, l.owner_id, lm.role, lm.created_at AS joined_at,
                    (SELECT COUNT(*) FROM saved_pages sp WHERE sp.library_id = l.id AND sp.status = "saved") AS page_count,
                    (SELECT COUNT(*) FROM tags t WHERE t.library_id = l.id) AS tag_count
               FROM libraries l
               JOIN library_members lm ON lm.library_id = l.id AND lm.user_id = :uid
              ORDER BY l.updated_at DESC',
            [':uid' => $userId],
        );
    }

    /** Them thanh vien vao thu vien (chi owner). */
    public static function addMember(int $libraryId, int $userId, LibraryRole $role): void
    {
        Database::execute(
            'INSERT INTO library_members (library_id, user_id, role) VALUES (:l, :u, :r)
             ON DUPLICATE KEY UPDATE role = :role_upd',
            [
                ':l' => $libraryId,
                ':u' => $userId,
                ':r' => $role->value,
                ':role_upd' => $role->value,
            ],
        );
    }

    public static function removeMember(int $libraryId, int $userId): void
    {
        Database::execute(
            'DELETE FROM library_members WHERE library_id = :l AND user_id = :u',
            [':l' => $libraryId, ':u' => $userId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function membersOf(int $libraryId): array
    {
        return Database::all(
            'SELECT lm.user_id, lm.role, u.username, u.email, u.display_name
               FROM library_members lm
               JOIN users u ON u.id = lm.user_id
              WHERE lm.library_id = :l
              ORDER BY FIELD(lm.role, "owner", "editor", "viewer"), u.username',
            [':l' => $libraryId],
        );
    }

    public static function isOwner(int $libraryId, ?int $userId = null): bool
    {
        $row = Database::first(
            'SELECT 1 AS ok FROM libraries WHERE id = :l AND owner_id = :u',
            [':l' => $libraryId, ':u' => $userId ?? Auth::id() ?? 0],
        );

        return $row !== null;
    }
}