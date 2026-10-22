<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Ket qua kiem tra mot dia chi URL.
 */
final class UrlValidationResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $reasonCode,
        public readonly string $message,
        public readonly ?string $normalizedUrl = null,
        public readonly ?string $scheme = null,
        public readonly ?string $host = null,
        public readonly ?int $port = null,
        /** @var array<int,string> */
        public readonly array $resolvedIps = [],
    ) {
    }

    public static function allowed(string $normalized, string $scheme, string $host, int $port, array $ips): self
    {
        return new self(true, 'ok', 'URL hợp lệ và thuộc phạm vi cho phép.', $normalized, $scheme, $host, $port, $ips);
    }

    public static function denied(string $reasonCode, string $message): self
    {
        return new self(false, $reasonCode, $message);
    }
}