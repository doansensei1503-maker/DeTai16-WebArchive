<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Security\AllowList;
use App\Security\IpRange;
use PHPUnit\Framework\TestCase;

/**
 * Kiem tra phan loai dia chi IP (ho tro SSRF / DNS rebinding).
 */
final class IpRangeTest extends TestCase
{
    /** @dataProvider provideBlockedIps */
    public function testIpBiChanNhanDien(string $ip, string $expectedLabel): void
    {
        $reasons = IpRange::blockedReasons($ip);

        $this->assertContains($expectedLabel, $reasons, "IP {$ip} phai duoc nhan dien");
    }

    /** @return array<int,array{0:string,1:string}> */
    public static function provideBlockedIps(): array
    {
        return [
            ['127.0.0.1', 'Loopback'],
            ['127.1.2.3', 'Loopback'],
            ['10.0.0.1', 'Mạng riêng'],
            ['192.168.1.1', 'Mạng riêng'],
            ['172.16.5.4', 'Mạng riêng'],
            ['169.254.10.1', 'Link-local'],
            ['0.0.0.0', 'Mạng không xác định'],
            ['::1', 'Loopback'],
        ];
    }

    /** @dataProvider providePublicIps */
    public function testIpCongKhaiKhongBiChan(string $ip): void
    {
        $this->assertTrue(
            IpRange::isPublicIp($ip),
            "IP cong khai {$ip} khong phai bi chan",
        );
    }

    /** @return array<int,array{0:string}> */
    public static function providePublicIps(): array
    {
        return [
            ['8.8.8.8'],
            ['1.1.1.1'],
            ['93.184.216.34'],
        ];
    }

    public function testCidrContainHoatDong(): void
    {
        $this->assertTrue(IpRange::contains('127.0.0.0/8', '127.0.0.1'));
        $this->assertTrue(IpRange::contains('127.0.0.0/8', '127.255.255.254'));
        $this->assertFalse(IpRange::contains('127.0.0.0/8', '128.0.0.1'));
        $this->assertTrue(IpRange::contains('10.0.0.0/8', '10.42.7.9'));
        $this->assertFalse(IpRange::contains('10.0.0.0/8', '11.0.0.1'));
    }

    public function testAllowListKhopTenvCon(): void
    {
        $allowList = new AllowList(['host:example.com'], false);

        $this->assertTrue($allowList->matchesHost('example.com'));
        $this->assertTrue($allowList->matchesHost('www.example.com'));
        $this->assertFalse($allowList->matchesHost('evil.com'));
        $this->assertFalse($allowList->matchesHost('example.com.evil.com'));
    }
}