<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\FetchStatus;
use App\Service\ArticleExtractor;
use PHPUnit\Framework\TestCase;

/**
 * CN2B + CN2C: Trich xuat va kiem tra chat luong noi dung.
 */
final class ArticleExtractorTest extends TestCase
{
    private const BASE_URL = 'http://127.0.0.1:8080/pages/01-article-hop-le.html';

    public function testTrichXuatBaiVietHopLe(): void
    {
        $html = $this->loadFixture('01-article-hop-le.html');

        $result = ArticleExtractor::extract($html, self::BASE_URL);

        $this->assertTrue($result['success'], 'Bai viet hop le phai trich xuat duoc: ' . ($result['message'] ?? ''));
        $this->assertSame('Lập trình PHP hướng đối tượng: Những điều cần biết', $result['title']);
        // layout_used cho biet bo cuc HTML nao da duoc dung.
        $this->assertSame('article', $result['layout_used']);
        // Noi dung duoc tach ra tu <article> (khong con the bao ngoai).
        $this->assertStringNotContainsString('<article', $result['content_html']);
        $this->assertGreaterThan(500, mb_strlen($result['content_text'], 'UTF-8'));
    }

    public function testTieuDeVaThanBaiDuocPhanBiet(): void
    {
        $html = $this->loadFixture('01-article-hop-le.html');

        $result = ArticleExtractor::extract($html, self::BASE_URL);

        $this->assertTrue($result['success']);

        // Tieu de duoc tach rieng thanh mot truong rieng.
        $this->assertNotSame('', $result['title']);

        // <h1> trong vung noi dung da duoc tach khoi than bai
        // (noi dung chi con cac tieu de con h2/h3 va van ban).
        $this->assertStringNotContainsString('<h1', $result['content_html']);
        $this->assertStringNotContainsString('nhiều thẻ HTML phức tạp', $result['content_html']);

        // Than bai van con cac doan van va tieu de con.
        $this->assertGreaterThan(3, substr_count($result['content_html'], '<p'));
        $this->assertGreaterThan(2, substr_count($result['content_html'], '<h2'));
    }

    public function testScriptVaThuocTinhSuKienDuocLamSachPhiaServer(): void
    {
        // CN2 giu nguyen HTML tho; CN3 (SnapshotService) moi loai bo.
        // Test nay chung minh HTML tho van chua script (khong bi gia dinh an toan),
        // va viec lam sach that su suoc dien ra o tang Snapshot.
        $html = $this->loadFixture('07-trang-chua-script.html');

        $result = ArticleExtractor::extract($html, 'http://127.0.0.1:8080/pages/07-trang-chua-script.html');

        $this->assertTrue($result['success']);
        // Tieu de da duoc tach rieng thanh truong 'title'.
        $this->assertSame('Bài viết chứa script và thuộc tính sự kiện', $result['title']);
        // Noi dung bai viet van con tho (chua qua sanitizer tang CN3).
        $this->assertStringContainsString('Trang nay co chu dich dua vao script', $result['content_html']);
    }

    public function testThanhPhanPhuBiLoaiBo(): void
    {
        $html = $this->loadFixture('01-article-hop-le.html');

        $result = ArticleExtractor::extract($html, self::BASE_URL);

        $this->assertTrue($result['success']);
        // Menu va footer khong duoc co trong noi dung bai viet.
        $this->assertStringNotContainsString('<nav>', $result['content_html']);
        $this->assertStringNotContainsString('<footer>', $result['content_html']);
        // Sidebar "Bai viet lien quan" khong duoc lan vao bai viet.
        $this->assertStringNotContainsString('Bài viết liên quan', $result['content_html']);
    }

    public function testTrang404BiTuChoi(): void
    {
        $html = $this->loadFixture('06-loi-404.html');

        $result = ArticleExtractor::extract($html, 'http://127.0.0.1:8080/pages/06-loi-404.html');

        $this->assertFalse($result['success'], 'Trang 404 khong duoc coi la bai viet hop le');
        $this->assertSame(FetchStatus::NoArticle, $result['status']);
    }

    public function testTrangKhongCoNoiDungHopLeBiTuChoi(): void
    {
        $html = $this->loadFixture('05-trang-khong-co-noi-dung.html');

        $result = ArticleExtractor::extract($html, 'http://127.0.0.1:8080/pages/05-trang-khong-co-noi-dung.html');

        $this->assertFalse($result['success']);
        $this->assertSame(FetchStatus::NoArticle, $result['status']);
        $this->assertStringContainsString('bố cục', $result['message']);
    }

    public function testBaiVietQuaNganBiTuChoi(): void
    {
        $html = $this->loadFixture('13-bai-viet-qua-ngan.html');

        $result = ArticleExtractor::extract($html, 'http://127.0.0.1:8080/pages/13-bai-viet-qua-ngan.html');

        $this->assertFalse($result['success'], 'Bai viet qua ngan phai bi tu choi');
        $this->assertSame(FetchStatus::NoArticle, $result['status']);
    }

    public function testHaiBaiCungTieuDeKhongBiGopNham(): void
    {
        $htmlA = $this->loadFixture('03-hai-bai-cung-tieu-de-a.html');
        $htmlB = $this->loadFixture('04-hai-bai-cung-tieu-de-b.html');

        $resultA = ArticleExtractor::extract($htmlA, 'http://127.0.0.1:8080/pages/03-hai-bai-cung-tieu-de-a.html');
        $resultB = ArticleExtractor::extract($htmlB, 'http://127.0.0.1:8080/pages/04-hai-bai-cung-tieu-de-b.html');

        $this->assertTrue($resultA['success']);
        $this->assertTrue($resultB['success']);

        // Cung tieu de...
        $this->assertSame($resultA['title'], $resultB['title']);

        // ... nhung noi dung phai khac nhau.
        $this->assertNotSame(
            $resultA['content_text'],
            $resultB['content_text'],
            'Hai bai cung tieu de nhung noi dung khac nhau phai trich xuat ra noi dung khac nhau',
        );

        $this->assertStringContainsString('cài đặt Ubuntu Server', $resultA['content_text']);
        $this->assertStringContainsString('mô hình bảo mật thông tin', $resultB['content_text']);
    }

    public function testAnhDuocNhanDienTrongNoiDung(): void
    {
        $html = $this->loadFixture('02-bai-viet-co-anh.html');

        $result = ArticleExtractor::extract($html, 'http://127.0.0.1:8080/pages/02-bai-viet-co-anh.html');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('<img', $result['content_html']);
        $this->assertStringContainsString('/images/dong-que-1.jpg', $result['content_html']);
    }

    public function testUnicodeTiengVietDuocGiuNguyen(): void
    {
        $html = $this->loadFixture('10-tieng-viet-unicode.html');

        $result = ArticleExtractor::extract($html, 'http://127.0.0.1:8080/pages/10-tieng-viet-unicode.html');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Tiếng Việt', $result['content_text']);
        $this->assertStringContainsString('ngôn ngữ phức tạp', $result['content_text']);
        // Ky tu dac biet
        $this->assertStringContainsString('«', $result['content_text']);
    }

    public function testHTMLPhucTapGiuDuCauTrucBangVaDanhSach(): void
    {
        $html = $this->loadFixture('14-bai-viet-html-phuc-tap.html');

        $result = ArticleExtractor::extract($html, 'http://127.0.0.1:8080/pages/14-bai-viet-html-phuc-tap.html');

        $this->assertTrue($result['success']);

        // DOMDocument ho khoi cac the rong thanh <blockquote cite="...">.
        $has = static fn (string $tag): bool => preg_match('#<' . $tag . '\b#i', $result['content_html']) === 1;

        $this->assertTrue($has('table'), 'Bang phai duoc giu lai');
        $this->assertTrue($has('th'), 'O tieu de bang phai duoc giu lai');
        $this->assertTrue($has('ol'), 'Danh sach co thu tu phai duoc giu lai');
        $this->assertTrue($has('ul'), 'Danh sach khong thu tu phai duoc giu lai');
        $this->assertTrue($has('blockquote'), 'Trich dan phai duoc giu lai');
        $this->assertTrue($has('pre'), 'Khoi ma phai duoc giu lai');
        $this->assertTrue($has('code'), 'The code phai duoc giu lai');
        $this->assertTrue($has('h3'), 'Tieu de cap 3 phai duoc giu lai');
        $this->assertFalse($has('script'), 'Script phai duoc loai bo');
    }

    public function testTacGiaVaNgayDangDuocTrichXuat(): void
    {
        $html = $this->loadFixture('01-article-hop-le.html');

        $result = ArticleExtractor::extract($html, self::BASE_URL);

        $this->assertTrue($result['success']);
        $this->assertSame('Nguyễn Văn An', $result['author']);
        $this->assertNotNull($result['published_at']);
        $this->assertStringStartsWith('2024-01-15', (string) $result['published_at']);
    }

    public function testKhongDuaToanBoHTMLLamNoiDungBaiViet(): void
    {
        // Trang chi co <div class="shell"> - bo cuc khong ho tro.
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><title>Trang khong dung bo cuc ho tro</title></head>
<body><div class="shell"><div class="content">
<p>' . str_repeat('Day la noi dung van ban rat dai nhung trong the div khong dung bo cuc ho tro nao ca. ', 12) . '</p>
</div></div></body></html>
HTML;

        $result = ArticleExtractor::extract($html, 'http://127.0.0.1:8080/khong-ho-tro');

        $this->assertFalse($result['success'],
            'Khong duoc gia dinh toan bo HTML la noi dung bai viet');
        $this->assertSame(FetchStatus::NoArticle, $result['status']);
    }

    public function testScriptVaIframeBiLoaiBoKhoiNoiDung(): void
    {
        $html = $this->loadFixture('07-trang-chua-script.html');

        $result = ArticleExtractor::extract($html, 'http://127.0.0.1:8080/pages/07-trang-chua-script.html');

        $this->assertTrue($result['success']);
        // Thanh phan khong phai noi dung bai viet (script, nav, footer) bi loai bo o buoc trich xuat.
        $this->assertStringNotContainsString('<script', $result['content_html']);
        $this->assertStringNotContainsString('document.write', $result['content_html']);
        $this->assertStringNotContainsString('evil.example.com', $result['content_html']);
    }

    private function loadFixture(string $name): string
    {
        $path = dirname(__DIR__, 2) . '/test_server/pages/' . $name;
        $this->assertFileExists($path, "Khong tim thay trang thu nghiem: {$name}");

        $content = file_get_contents($path);
        $this->assertIsString($content);

        return $content;
    }
}