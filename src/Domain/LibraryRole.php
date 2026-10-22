<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Vai tro cua nguoi dung trong mot thu vien.
 * - owner : quan ly thu vien, thanh vien, noi dung
 * - editor: duoc luu bai, gan nhan, xem noi dung
 * - viewer: chi xem noi dung
 */
enum LibraryRole: string
{
    case Owner  = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner  => 'Chủ sở hữu',
            self::Editor => 'Biên tập viên',
            self::Viewer => 'Người xem',
        };
    }

    public function canRead(): bool
    {
        return true;
    }

    public function canWrite(): bool
    {
        return $this === self::Owner || $this === self::Editor;
    }

    public function canManageLibrary(): bool
    {
        return $this === self::Owner;
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(static fn (self $r): string => $r->value, self::cases());
    }
}