<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Security\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * CN3B: Lam sach HTML phia server.
 */
final class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer();
    }

    public function testScriptVaNoiDungBiLoaiBo(): void
    {
        $dirty = '<p>Van ban an toan</p><script>alert("xss")</script>';

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('Van ban an toan', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert', $clean);
    }

    public function testScriptBenNgoaiBiLoaiBo(): void
    {
        $dirty = '<p>Noi dung</p><script src="https://evil.example.com/x.js"></script>';

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('evil.example.com', $clean);
        $this->assertStringNotContainsString('<script', $clean);
    }

    /** @dataProvider provideEventAttributes */
    public function testThuocTinhSuKienBiLoaiBo(string $attr): void
    {
        $dirty = "<p {$attr}=\"alert('xss')\">Van ban</p>";

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('Van ban', $clean);
        $this->assertStringNotContainsString($attr . '=', $clean, "Thuoc tinh {$attr} phai bi loai bo");
    }

    /** @return array<int,array{0:string}> */
    public static function provideEventAttributes(): array
    {
        return [
            ['onclick'], ['onload'], ['onerror'], ['onmouseover'], ['onfocus'],
            ['onblur'], ['onsubmit'], ['onchange'], ['onanimationstart'],
        ];
    }

    public function testIframeObjectEmbedBiLoaiBo(): void
    {
        $dirty = <<<'HTML'
<p>Giu lai</p>
<iframe src="https://evil.example.com"></iframe>
<object data="evil.swf"></object>
<embed src="evil.swf">
<form action="/hack"><input name="x"></form>
HTML;

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('Giu lai', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('<object', $clean);
        $this->assertStringNotContainsString('<embed', $clean);
        $this->assertStringNotContainsString('<form', $clean);
        $this->assertStringNotContainsString('evil.example.com', $clean);
    }

    public function testJavaScriptUrlBiLoaiBo(): void
    {
        $dirty = '<p><a href="javascript:alert(1)">Bam</a></p>';

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('Bam', $clean);
    }

    public function testDataUrlBiLoaiBo(): void
    {
        $dirty = '<a href="data:text/html;base64,PHNjcmlwdD4=">Link</a>';

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('data:text/html', $clean);
    }

    public function testStyleVaLinkBiLoaiBo(): void
    {
        $dirty = <<<'HTML'
<style>body { background: url(https://evil.com/x.png); }</style>
<link rel="stylesheet" href="https://cdn.evil.com/style.css">
<p>Van ban giu lai</p>
HTML;

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('Van ban giu lai', $clean);
        $this->assertStringNotContainsString('<style', $clean);
        $this->assertStringNotContainsString('<link', $clean);
        $this->assertStringNotContainsString('cdn.evil.com', $clean);
    }

    public function testThuoctinhStyleInlineBiLoaiBo(): void
    {
        $dirty = '<p style="background:url(https://evil.com/x.png)">Van ban</p>';

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('style=', $clean);
        $this->assertStringNotContainsString('evil.com', $clean);
    }

    public function testSvgVaMathBiLoaiBo(): void
    {
        $dirty = '<p>Text</p><svg><script>alert(1)</script></svg><math><mi>x</mi></math>';

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('Text', $clean);
        $this->assertStringNotContainsString('<svg', $clean);
        $this->assertStringNotContainsString('<math', $clean);
    }

    public function testLinkNgoaiDuocThemNoopener(): void
    {
        $dirty = '<a href="https://example.com/bai-viet">Bai viet</a>';

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('href="https://example.com/bai-viet"', $clean);
        $this->assertStringContainsString('noopener', $clean);
    }

    public function testAnhKhongDuocLuuKhongTaiLaiTuNguon(): void
    {
        // Khong truyen imageMap -> src phai bi loai bo.
        $dirty = '<p><img src="http://127.0.0.1:8080/images/anh-1.png" alt="Anh"></p>';

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('127.0.0.1:8080', $clean,
            'Ban doc lai khong duoc tai anh tu trang nguon');
        $this->assertStringNotContainsString('src=', $clean);
        $this->assertStringContainsString('alt="Anh"', $clean);
    }

    public function testAnhDaLuuDuocThayBangDuongDanNoiBo(): void
    {
        $imageMap = ['http://127.0.0.1:8080/images/anh-1.png' => '/asset/abc123'];
        $dirty = '<p><img src="http://127.0.0.1:8080/images/anh-1.png" alt="Anh"></p>';

        $clean = $this->sanitizer->sanitize($dirty, $imageMap);

        $this->assertStringContainsString('/asset/abc123', $clean);
        $this->assertStringNotContainsString('127.0.0.1:8080', $clean);
    }

    public function testTheNoiDungDuoocGiuLai(): void
    {
        $dirty = <<<'HTML'
<h2>Tieu de</h2>
<p><strong>Chu dam</strong> va <em>chu nghieng</em></p>
<ul><li>Muc 1</li><li>Muc 2</li></ul>
<table><tr><th>Tieu de cot</th><td colspan="2">Du lieu</td></tr></table>
<pre><code>echo "hello";</code></pre>
<blockquote>Trich dan</blockquote>
HTML;

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('<h2>', $clean);
        $this->assertStringContainsString('<strong>', $clean);
        $this->assertStringContainsString('<ul>', $clean);
        $this->assertStringContainsString('colspan="2"', $clean);
        $this->assertStringContainsString('<pre>', $clean);
        $this->assertStringContainsString('<blockquote>', $clean);
    }

    public function testTheKhongChoPhepDuocGiaiThe(): void
    {
        // <marquee> bi loai hoan toan, <custom-tag> duoc giai the.
        $dirty = '<custom-tag>Van ban ben trong</custom-tag>';

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('Van ban ben trong', $clean);
        $this->assertStringNotContainsString('custom-tag', $clean);
    }

    public function testMetaHttpEquivRefreshBiLoaiBo(): void
    {
        $dirty = '<meta http-equiv="refresh" content="0;url=https://evil.com"><p>Text</p>';

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('refresh', $clean);
        $this->assertStringNotContainsString('evil.com', $clean);
    }

    public function testUnicodeTiengVietDuocGiuNguyen(): void
    {
        $dirty = '<p>Tiếng Việt đầy đủ dấu: Đỗ Mười, Nguyễn, Trần, Phạm, Hoàng, Huỳnh, Phan</p>';

        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('Tiếng Việt đầy đủ dấu', $clean);
        $this->assertStringContainsString('Đỗ Mười', $clean);
        $this->assertStringContainsString('Huỳnh', $clean);
    }
}