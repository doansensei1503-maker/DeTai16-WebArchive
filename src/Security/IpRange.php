<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Tien ich kiem tra dia chi IP: public / private / loopback / reserved.
 * Dung de chan SSRF va DNS rebinding.
 */
final class IpRange
{
    /** Kiem tra IP co phai IPv4 khong. */
    public static function isIpv4(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    public static function isIpv6(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /** Chuyen IPv4 thanh so nguyen lon. */
    public static function ipv4ToLong(string $ip): int
    {
        $long = ip2long($ip);

        return $long === false ? 0 : ($long & 0xFFFFFFFF);
    }

    /** @return array{0:int,1:int}|null [network, broadcast] */
    public static function parseCidr(string $cidr): ?array
    {
        if (!str_contains($cidr, '/')) {
            $ip = trim($cidr);
            if (self::isIpv4($ip)) {
                $long = self::ipv4ToLong($ip);

                return [$long, $long];
            }
            $packed = @inet_pton($ip);
            if ($packed === false) {
                return null;
            }
            $bin = $thisBits = strlen($packed) * 8;

            return [$thisBits, $thisBits];
        }

        [$subnet, $maskBits] = explode('/', $cidr, 2);
        $subnet = trim($subnet);
        $maskBits = (int) $maskBits;

        if (!self::isIpv4($subnet)) {
            $packed = @inet_pton($subnet);
            if ($packed === false || $maskBits < 0 || $maskBits > 128) {
                return null;
            }

            return self::cidrFromBytes($packed, $maskBits);
        }

        if ($maskBits < 0 || $maskBits > 32) {
            return null;
        }

        $ipLong = self::ipv4ToLong($subnet);
        $mask = $maskBits === 0 ? 0 : (0xFFFFFFFF << (32 - $maskBits)) & 0xFFFFFFFF;
        $network = $ipLong & $mask;
        $broadcast = $network | (~$mask & 0xFFFFFFFF);

        return [$network, $broadcast];
    }

    /** @return array{0:int,1:int}|null */
    private static function cidrFromBytes(string $packed, int $maskBits): ?array
    {
        $bytes = array_values(unpack('C*', $packed) ?: []);
        $totalBits = strlen($packed) * 8;
        $value = 0;
        for ($i = 0; $i < $totalBits; $i++) {
            $bit = $maskBits > $i ? (($bytes[intdiv($i, 8)] >> (7 - ($i % 8))) & 1) : 0;
            $value |= $bit << ($totalBits - 1 - $i);
        }
        $network = $value;
        $broadcast = 0;
        for ($i = 0; $i < $totalBits; $i++) {
            $bit = $maskBits > $i ? (($bytes[intdiv($i, 8)] >> (7 - ($i % 8))) & 1) : 1;
            $broadcast |= $bit << ($totalBits - 1 - $i);
        }

        return [$network, $broadcast];
    }

    /** Kiem tra IP co nam trong CIDR hay khong. */
    public static function contains(string $cidr, string $ip): bool
    {
        $range = self::parseCidr($cidr);
        if ($range === null) {
            return false;
        }

        if (self::isIpv4($ip) && $range[1] <= 0xFFFFFFFF) {
            $long = self::ipv4ToLong($ip);

            return $long >= $range[0] && $long <= $range[1];
        }

        if (self::isIpv6($ip)) {
            $packedIp = @inet_pton($ip);
            $packedNet = @inet_pton(explode('/', $cidr)[0]);
            if ($packedIp !== false && $packedNet !== false && strlen($packedIp) === strlen($packedNet)) {
                return ($packedNet & $packedIp) === $packedNet;
            }
        }

        return false;
    }

    /** @return array<int,string> danh sach lý do IP bi chan */
    public static function blockedReasons(string $ip): array
    {
        $reasons = [];

        if (!self::isIpv4($ip) && !self::isIpv6($ip)) {
            return ['Địa chỉ IP không hợp lệ'];
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return [];
        }

        foreach (self::reservedCidrs() as [$cidr, $label]) {
            if (self::contains($cidr, $ip)) {
                $reasons[] = $label;
            }
        }

        return $reasons;
    }

    public static function isPublicIp(string $ip): bool
    {
        return self::blockedReasons($ip) === [];
    }

    public static function isLoopback(string $ip): bool
    {
        $reasons = self::blockedReasons($ip);

        return $reasons !== [] && in_array('Loopback', $reasons, true);
    }

    public static function isPrivate(string $ip): bool
    {
        $reasons = self::blockedReasons($ip);

        return $reasons !== [] && in_array('Mạng riêng', $reasons, true);
    }

    /** @return array<int,array{0:string,1:string}> */
    private static function reservedCidrs(): array
    {
        return [
            ['0.0.0.0/8', 'Mạng không xác định'],
            ['10.0.0.0/8', 'Mạng riêng'],
            ['100.64.0.0/10', 'Mạng riêng'],
            ['127.0.0.0/8', 'Loopback'],
            ['169.254.0.0/16', 'Link-local'],
            ['172.16.0.0/12', 'Mạng riêng'],
            ['192.0.0.0/24', 'Mạng riêng'],
            ['192.168.0.0/16', 'Mạng riêng'],
            ['198.18.0.0/15', 'Mạng riêng'],
            ['224.0.0.0/4', 'Multicast'],
            ['240.0.0.0/4', 'Dự phòng'],
            ['255.255.255.255/32', 'Broadcast'],
            ['::1/128', 'Loopback'],
            ['fc00::/7', 'Mạng riêng'],
            ['fe80::/10', 'Link-local'],
            ['::/128', 'Mạng không xác định'],
        ];
    }
}