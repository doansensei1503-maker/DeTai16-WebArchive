<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Security\AllowList;
use App\Security\IpRange;
use App\Security\UrlValidator;
use PHPUnit\Framework\TestCase;

/**
 * CN1D: Kiem tra URL va danh sach cho phep.
 */
final class UrlValidatorTest extends TestCase
{
    private function validator(bool $allowPrivate = true): UrlValidator
    {
        $allowList = new AllowList([
            'scheme:http,https',
            'port:8080',
            'cidrs:127.0.0.0/8',
            'host:localhost,127.0.0.1',
        ], $allowPrivate);

        return new UrlValidator($allowList);
    }

    public function testUrlHopLeDuocPhep(): void
    {
        $result = $this->validator()->validate('http://127.0.0.1:8080/pages/01-article-hop-le.html');

        $this->assertTrue($result->allowed, 'URL hop le phai duoc phep');
        $this->assertSame('127.0.0.1', $result->host);
        $this->assertSame(8080, $result->port);
        $this->assertSame('http', $result->scheme);
    }

    public function testUrlRongBiTuChoi(): void
    {
        $result = $this->validator()->validate('   ');

        $this->assertFalse($result->allowed);
        $this->assertSame('empty', $result->reasonCode);
    }

    /** @dataProvider provideMalformedUrls */
    public function testUrlSaiDinhDangBiTuChoi(string $url): void
    {
        $result = $this->validator()->validate($url);

        $this->assertFalse($result->allowed, "URL '{$url}' phai bi tu choi");
    }

    /** @return array<int,array{0:string}> */
    public static function provideMalformedUrls(): array
    {
        return [
            ['khong-phai-la-url'],
            ['http://'],
            ['://example.com'],
            ['ht!tp://localhost:8080/x'],
        ];
    }

    /** @dataProvider provideDisallowedSchemes */
    public function testGiaoThucKhongHoTroBiTuChoi(string $url, string $expectedCode): void
    {
        $result = $this->validator()->validate($url);

        $this->assertFalse($result->allowed, "Giao thuc cua '{$url}' phai bi tu choi");
        $this->assertSame($expectedCode, $result->reasonCode);
    }

    /** @return array<int,array{0:string,1:string}> */
    public static function provideDisallowedSchemes(): array
    {
        return [
            ['file:///C:/Windows/System32/hosts', 'scheme_not_allowed'],
            ['ftp://127.0.0.1:8080/x.txt', 'scheme_not_allowed'],
            ['gopher://127.0.0.1:8080/x', 'scheme_not_allowed'],
            ['data:text/html,<h1>x</h1>', 'scheme_not_allowed'],
        ];
    }

    public function testDomainNgoaiDanhSachBiTuChoi(): void
    {
        $result = $this->validator()->validate('http://example.com:8080/bai-viet');

        $this->assertFalse($result->allowed);
        $this->assertSame('host_not_allowed', $result->reasonCode);
    }

    public function testPortNgoaiDanhSachBiTuChoi(): void
    {
        $result = $this->validator()->validate('http://127.0.0.1:9999/bai-viet');

        $this->assertFalse($result->allowed);
        $this->assertSame('port_not_allowed', $result->reasonCode);
    }

    public function testChuaThongTinDangNhapBiTuChoi(): void
    {
        $result = $this->validator()->validate('http://user:pass@127.0.0.1:8080/x');

        $this->assertFalse($result->allowed);
        $this->assertSame('credentials_in_url', $result->reasonCode);
    }

    public function testKichTuDieuKhienBiTuChoi(): void
    {
        $result = $this->validator()->validate("http://127.0.0.1:8080/x\nSet-Cookie: a=b");

        $this->assertFalse($result->allowed);
        $this->assertSame('control_chars', $result->reasonCode);
    }

    public function testUrlQuaDaiBiTuChoi(): void
    {
        $long = 'http://127.0.0.1:8080/' . str_repeat('a', 2100);
        $result = $this->validator()->validate($long);

        $this->assertFalse($result->allowed);
        $this->assertSame('too_long', $result->reasonCode);
    }

    public function testLoopbackBiChanKhiKhongChoPhepMangRieng(): void
    {
        $validator = $this->validator(allowPrivate: false);
        $result = $validator->validate('http://127.0.0.1:8080/x');

        $this->assertFalse($result->allowed, 'Khi tat ALLOW_PRIVATE_NETWORK thi loopback phai bi chan');
        $this->assertSame('blocked_ip', $result->reasonCode);
    }

    public function testIpRiengBiChanKhiKhongChoPhepMangRieng(): void
    {
        $allowList = new AllowList([
            'scheme:http,https',
            'port:8080',
            'cidrs:10.0.0.0/8,127.0.0.0/8',
            'host:localhost,127.0.0.1,10.0.0.5',
        ], false);

        $result = (new UrlValidator($allowList))->validate('http://10.0.0.5:8080/x');

        $this->assertFalse($result->allowed);
        $this->assertSame('blocked_ip', $result->reasonCode);
    }

    public function testNormalizedUrlChuanHoaDung(): void
    {
        $result = $this->validator()->validate('HTTP://127.0.0.1:8080/pages/test.html?b=2&a=1');

        $this->assertTrue($result->allowed);
        $this->assertSame('http://127.0.0.1:8080/pages/test.html?a=1&b=2', $result->normalizedUrl);
    }
}