<?php

declare(strict_types=1);

namespace App\Security;

/**
 * DANH SACH URL DUOC PHEP LUU.
 *
 * Cau hinh qua bien moi truong ALLOWED_FETCH_TARGETS, dinh dang:
 *   <ten_mien>|<scheme:http,https>|<port:8080>|<cidrs:127.0.0.0/8>|<host:...>
 *
 * Vi du trong .env:
 *   ALLOWED_FETCH_TARGETS=127.0.0.1:8080|scheme:http,https|port:8080|cidrs:127.0.0.0/8
 */
final class AllowList
{
    /** @var array<int,string> */
    private array $hosts = [];

    /** @var array<int,string> */
    private array $schemes = ['http', 'https'];

    /** @var array<int,int> */
    private array $ports = [];

    /** @var array<int,string> CIDR bang IPv4 */
    private array $cidrs = [];

    /** @var array<int,string> */
    private array $hostPatterns = [];

    private bool $allowPrivateNetwork;

    /** @var array<int,string> */
    private array $raw = [];

    /** @param array<int,string> $entries */
    public function __construct(array $entries, bool $allowPrivateNetwork = false)
    {
        $this->allowPrivateNetwork = $allowPrivateNetwork;
        $this->raw = $entries;

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $this->parseEntry($entry);
        }
    }

    /** Doc danh sach tu config. */
    public static function fromConfig(): self
    {
        $raw = \App\Support\Env::get('ALLOWED_FETCH_TARGETS', '');

        /** @var array<int,string> $entries */
        $entries = $raw === null || trim($raw) === ''
            ? []
            : array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $raw) ?: [])));

        return new self($entries, \App\Support\Env::bool('ALLOW_PRIVATE_NETWORK', false));
    }

    private function parseEntry(string $entry): void
    {
        if (str_starts_with($entry, 'scheme:')) {
            foreach (explode(',', substr($entry, 7)) as $s) {
                $s = strtolower(trim($s));
                if ($s !== '') {
                    $this->schemes[] = $s;
                }
            }

            return;
        }

        if (str_starts_with($entry, 'port:')) {
            foreach (explode(',', substr($entry, 5)) as $p) {
                $p = (int) trim($p);
                if ($p > 0) {
                    $this->ports[] = $p;
                }
            }

            return;
        }

        if (str_starts_with($entry, 'cidrs:') || str_starts_with($entry, 'cidr:')) {
            $value = substr($entry, str_starts_with($entry, 'cidrs:') ? 6 : 5);
            foreach (explode(',', $value) as $cidr) {
                $cidr = trim($cidr);
                if ($cidr !== '') {
                    $this->cidrs[] = $cidr;
                }
            }

            return;
        }

        if (str_starts_with($entry, 'host:')) {
            $value = substr($entry, 5);
            foreach (explode(',', $value) as $h) {
                $h = strtolower(trim($h));
                if ($h !== '') {
                    $this->hostPatterns[] = $h;
                }
            }

            return;
        }

        // Dang "ten_mien" hoac "ten_mien:port"
        $this->hosts[] = strtolower($entry);
    }

    public function isSchemeAllowed(string $scheme): bool
    {
        return in_array(strtolower($scheme), array_unique($this->schemes), true);
    }

    public function isPortAllowed(?int $port, string $scheme): bool
    {
        if ($this->ports === []) {
            return true;
        }

        $effective = $port ?? (strtolower($scheme) === 'https' ? 443 : 80);

        return in_array($effective, $this->ports, true);
    }

    public function matchesHost(string $host): bool
    {
        $host = strtolower(trim($host, '.'));
        if ($host === '') {
            return false;
        }

        foreach ($this->hosts as $pattern) {
            if ($this->matchPattern($pattern, $host)) {
                return true;
            }
        }

        foreach ($this->hostPatterns as $pattern) {
            if ($this->matchPattern($pattern, $host)) {
                return true;
            }
        }

        return false;
    }

    private function matchPattern(string $pattern, string $host): bool
    {
        // "example.com" khop ca "example.com" va "www.example.com"
        if (!str_contains($pattern, '*')) {
            return $host === $pattern || str_ends_with($host, '.' . $pattern);
        }

        $regex = '/^' . str_replace('\*', '[^.]*', preg_quote($pattern, '/')) . '$/';

        return (bool) preg_match($regex, $host);
    }

    /** Kiem tra mot dia chi IP co nam trong danh sach CIDR cho phep hay khong. */
    public function ipAllowed(string $ip): bool
    {
        if ($this->cidrs === []) {
            return true;
        }

        foreach ($this->cidrs as $cidr) {
            if (IpRange::contains($cidr, $ip)) {
                return true;
            }
        }

        return false;
    }

    public function allowsPrivateNetwork(): bool
    {
        return $this->allowPrivateNetwork;
    }

    /** @return array<int,string> */
    public function allowedSchemes(): array
    {
        return array_values(array_unique($this->schemes));
    }

    /** @return array<int,int> */
    public function allowedPorts(): array
    {
        return array_values(array_unique($this->ports));
    }

    /** @return array<int,string> */
    public function hostsList(): array
    {
        return array_values(array_unique(array_merge($this->hosts, $this->hostPatterns)));
    }

    /** @return array<int,string> */
    public function cidrsList(): array
    {
        return array_values(array_unique($this->cidrs));
    }

    /** @return array<int,string> */
    public function rawEntries(): array
    {
        return $this->raw;
    }

    public function isEmpty(): bool
    {
        return $this->hosts === [] && $this->hostPatterns === [];
    }
}