<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Nguoi dung dang dang nhap.
 */
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $displayName,
        public readonly bool $isActive,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['email'],
            (string) ($row['display_name'] ?? ''),
            (bool) ($row['is_active'] ?? 1),
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'username'     => $this->username,
            'email'        => $this->email,
            'display_name' => $this->displayName,
            'is_active'    => $this->isActive,
        ];
    }
}