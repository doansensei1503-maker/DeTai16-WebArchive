<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\FetchStatus;
use App\Domain\LibraryRole;
use App\Security\AllowList;
use App\Security\HtmlSanitizer;
use App\Security\UrlValidator;
use App\Service\AccessControl;
use App\Service\ArticleFetcher;
use App\Service\ArticleService;
use App\Service\AssetStorage;
use App\Service\LibraryService;
use App\Service\SnapshotService;
use App\Service\TagService;
use App\Support\Database;
use Tests\DatabaseTestCase;

/**
 * KIEM THU TICH HOP: luong day du CN1 -> CN2 -> CN3.
 *
 * Can may chu thu nghiem chay tai 127.0.0.1:8080
 * (php -S 127.0.0.1:8080 -t test_server test_server/router.php)
 */
final class IntegrationFlowTest extends DatabaseTestCase
{
    private const BASE = 'http://127.0.0.1:8080';

    private function allowList(): AllowList
    {
        return new AllowList([
            'scheme:http,https',
            'port:8080',
            'cidrs:127.0.0.0/8',
            'host:127.0.0.1,localhost',
        ], true);
    }

    private function testServerUp(): bool
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::BASE . '/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return $code === 200 && $body !== false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        if (!$this->testServerUp()) {
            $this->markTestSkipped(
                'May chu thu nghiem chua chay. Hay chay: '
                . 'php -S 127.0.0.1:8080 -t test_server test_server/router.php',
            );
        }
    }

    public function testLuongDayDuLuuVaDocLai(): void
    {
        $userId = $this->createUser('it_flow_' . bin2hex(random_bytes(4)));

        // --- CN1: Tao thu vien + nhan.
        $libraryId = LibraryService::create($userId, 'Lib tich hop ' . bin2hex(random_bytes(4)));
        $tagId = TagService::create($libraryId, 'TichHop');

        // --- CN2: Kiem tra URL -> lay HTML -> trich xuat -> luu.
        $url = self::BASE . '/pages/02-bai-viet-co-anh.html';

        $validation = (new UrlValidator($this->allowList()))->validate($url);
        $this->assertTrue($validation->allowed, 'URL thu nghiem phai duoc phep');

        $fetch = (new ArticleFetcher($this->allowList()))->fetch($url);
        $this->assertTrue($fetch['success'], 'Phai lay duoc HTML: ' . ($fetch['message'] ?? ''));
        $this->assertSame(200, $fetch['http_code']);
        $this->assertStringContainsString('Hành trình khám phá', $fetch['title']);

        $pageId = ArticleService::save(
            $libraryId,
            $userId,
            $fetch['final_url'],
            $fetch['title'],
            $fetch['author'],
            $fetch['content_html'],
            $fetch['content_text'],
            $fetch['excerpt'],
            $fetch['published_at'],
            $fetch['language'],
            $fetch['layout_used'],
        );

        $this->assertGreaterThan(0, $pageId);

        // Du lieu da ghi dung vao MySQL.
        $page = ArticleService::find($pageId);
        $this->assertSame(FetchStatus::Saved->value, $page['status']);
        $this->assertSame($libraryId, (int) $page['library_id']);
        $this->assertStringContainsString('Đông Anh', $page['content_text']);
        $this->assertNotEmpty($page['saved_at']);
        $this->assertGreaterThan(0, (int) $page['word_count']);

        // --- CN1: Gan nhan cho bai da luu.
        TagService::attach($pageId, $tagId, $libraryId);
        $this->assertCount(1, TagService::tagsOfPage($pageId));

        // --- CN3: Tao ban doc lai (lay anh + lam sach HTML).
        $snapshotId = SnapshotService::create($pageId, $libraryId);
        $snapshot = SnapshotService::find($snapshotId);

        $this->assertNotNull($snapshot);
        $this->assertGreaterThan(0, (int) $snapshot['image_count'], 'Phai luu duoc anh cuc bo');
        $this->assertStringNotContainsString('<script', $snapshot['html']);
        $this->assertStringContainsString('/asset/', $snapshot['html'], 'Anh phai tro toi duong dan noi bo');
        $this->assertStringNotContainsString('127.0.0.1:8080/images', $snapshot['html'],
            'Ban doc lai khong duoc tro toi trang nguon');

        // Anh thuc su da duoc luu xuong dia.
        $assets = Database::all(
            'SELECT * FROM assets WHERE library_id = :l AND status = "saved"',
            [':l' => $libraryId],
        );
        $this->assertGreaterThan(0, count($assets));
        foreach ($assets as $asset) {
            $this->assertFileExists((string) $asset['local_path'], 'Tep anh phai ton tai tren dia');
            $this->assertContains((string) $asset['mime_type'], ['image/png', 'image/jpeg', 'image/webp']);
        }

        // --- Phan quyen: user khac khong doc duoc.
        $otherUser = $this->createUser('it_other_' . bin2hex(random_bytes(4)));
        $this->assertFalse(AccessControl::canRead($libraryId, $otherUser));
    }

    public function testTrangLoiKhongTaoBaiVietGia(): void
    {
        $userId = $this->createUser('it_err_' . bin2hex(random_bytes(4)));
        $libraryId = LibraryService::create($userId, 'Lib loi ' . bin2hex(random_bytes(4)));

        $before = (int) Database::value('SELECT COUNT(*) FROM saved_pages WHERE library_id = :l', [':l' => $libraryId]);

        $fetcher = new ArticleFetcher($this->allowList());

        // 404 that.
        $fetch404 = $fetcher->fetch(self::BASE . '/pages/06-loi-404.html');
        $this->assertFalse($fetch404['success']);
        $this->assertSame(FetchStatus::HttpError, $fetch404['status']);
        $this->assertSame(404, $fetch404['http_code']);

        // Trang khong co noi dung bai viet.
        $fetchNoArticle = $fetcher->fetch(self::BASE . '/pages/05-trang-khong-co-noi-dung.html');
        $this->assertFalse($fetchNoArticle['success']);
        $this->assertSame(FetchStatus::NoArticle, $fetchNoArticle['status']);

        $after = (int) Database::value('SELECT COUNT(*) FROM saved_pages WHERE library_id = :l', [':l' => $libraryId]);

        $this->assertSame($before, $after, 'URL loi khong duoc tao ra bai viet gia');
    }

    public function testChuyenHuongRaNgoaiBiChan(): void
    {
        $fetch = (new ArticleFetcher($this->allowList()))->fetch(self::BASE . '/redirect-ra-ngoai');

        $this->assertFalse($fetch['success']);
        $this->assertSame(FetchStatus::NotAllowed, $fetch['status']);
    }

    public function testChuyenHuongHopLeDuocTheoDoi(): void
    {
        $fetch = (new ArticleFetcher($this->allowList()))->fetch(self::BASE . '/redirect-hop-le');

        $this->assertTrue($fetch['success'], 'Chuyen huong trong pham vi phai duoc theo: ' . ($fetch['message'] ?? ''));
        $this->assertStringContainsString('01-article-hop-le.html', (string) $fetch['final_url']);
    }

    public function testURLNgoaiDanhSachBiTuChoi(): void
    {
        $fetch = (new ArticleFetcher($this->allowList()))->fetch('http://example.com/bai-viet');

        $this->assertFalse($fetch['success']);
        $this->assertSame(FetchStatus::NotAllowed, $fetch['status']);
    }

    public function testHaiBaiCungTieuDeLuuRiengBiet(): void
    {
        $userId = $this->createUser('it_dup_' . bin2hex(random_bytes(4)));
        $libraryId = LibraryService::create($userId, 'Lib trung ' . bin2hex(random_bytes(4)));

        $fetcher = new ArticleFetcher($this->allowList());

        $a = $fetcher->fetch(self::BASE . '/pages/03-hai-bai-cung-tieu-de-a.html');
        $b = $fetcher->fetch(self::BASE . '/pages/04-hai-bai-cung-tieu-de-b.html');

        $this->assertTrue($a['success']);
        $this->assertTrue($b['success']);
        $this->assertSame($a['title'], $b['title'], 'Hai bai cung tieu de');

        $pageA = ArticleService::save($libraryId, $userId, $a['final_url'], $a['title'],
            '', $a['content_html'], $a['content_text'], $a['excerpt'], null, 'vi', $a['layout_used']);
        $pageB = ArticleService::save($libraryId, $userId, $b['final_url'], $b['title'],
            '', $b['content_html'], $b['content_text'], $b['excerpt'], null, 'vi', $b['layout_used']);

        $this->assertNotSame($pageA, $pageB, 'Hai bai phai la hai ban ghi rieng');

        $recA = ArticleService::find($pageA);
        $recB = ArticleService::find($pageB);

        $this->assertSame($recA['title'], $recB['title']);
        $this->assertNotSame($recA['content_hash'], $recB['content_hash'],
            'Noi dung khac nhau phai co hash khac nhau');
        $this->assertNotSame($recA['source_url_hash'], $recB['source_url_hash']);
    }

    public function testUrlTrungTrongCungThuVienBiTuChoi(): void
    {
        $userId = $this->createUser('it_uniq_' . bin2hex(random_bytes(4)));
        $libraryId = LibraryService::create($userId, 'Lib unique ' . bin2hex(random_bytes(4)));

        $url = self::BASE . '/pages/01-article-hop-le.html';
        $html = '<article><h1>Tieu de</h1><p>' . str_repeat('Noi dung bai viet du de duong chuan. ', 10) . '</p></article>';

        ArticleService::save($libraryId, $userId, $url, 'Tieu de', '', $html, strip_tags($html), '', null, 'vi', 'article');

        $this->expectException(\InvalidArgumentException::class);
        ArticleService::save($libraryId, $userId, $url, 'Tieu de khac', '', $html, strip_tags($html), '', null, 'vi', 'article');
    }

    public function testBanDocLaiKhongChayScriptVaKhongTaiTaiNguonBenNgoai(): void
    {
        $userId = $this->createUser('it_xss_' . bin2hex(random_bytes(4)));
        $libraryId = LibraryService::create($userId, 'Lib xss ' . bin2hex(random_bytes(4)));

        $fetch = (new ArticleFetcher($this->allowList()))
            ->fetch(self::BASE . '/pages/07-trang-chua-script.html');
        $this->assertTrue($fetch['success']);

        $pageId = ArticleService::save($libraryId, $userId, $fetch['final_url'], $fetch['title'],
            '', $fetch['content_html'], $fetch['content_text'], $fetch['excerpt'], null, 'vi', $fetch['layout_used']);

        $snapshotId = SnapshotService::create($pageId, $libraryId);
        $snapshot = SnapshotService::find($snapshotId);
        $html = (string) $snapshot['html'];

        // Script va thuoc tinh su kien phai bi loai bo.
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror=', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('evil.example.com', $html);

        // Noi dung van con.
        $this->assertStringContainsString('Trang nay co chu dich', $html);

        // CSP khai bao chan script.
        $this->assertStringContainsString("script-src 'none'", (string) $snapshot['csp']);
    }

    public function testAnhKhongDuocLuuThiKhongTaiLaiTuNguon(): void
    {
        $userId = $this->createUser('it_img_' . bin2hex(random_bytes(4)));
        $libraryId = LibraryService::create($userId, 'Lib anh ' . bin2hex(random_bytes(4)));

        $fetch = (new ArticleFetcher($this->allowList()))
            ->fetch(self::BASE . '/pages/15-bai-viet-nhieu-anh.html');
        $this->assertTrue($fetch['success']);

        $pageId = ArticleService::save($libraryId, $userId, $fetch['final_url'], $fetch['title'],
            '', $fetch['content_html'], $fetch['content_text'], $fetch['excerpt'], null, 'vi', $fetch['layout_used']);

        $snapshotId = SnapshotService::create($pageId, $libraryId);
        $snapshot = SnapshotService::find($snapshotId);
        $html = (string) $snapshot['html'];

        // Anh CDN ben ngoai phai bi loai bo (khong tai lai).
        $this->assertStringNotContainsString('cdn.example.com', $html);
        $this->assertStringNotContainsString('127.0.0.1:8080/images', $html);

        // Anh hop le da luu phai tro toi duong dan noi bo.
        $this->assertStringContainsString('/asset/', $html);

        // Trang co anh khong ton tai -> phai ghi nhan trang thai that bai.
        $failed = Database::all(
            'SELECT * FROM assets WHERE library_id = :l AND status = "failed"',
            [':l' => $libraryId],
        );
        $this->assertGreaterThan(0, count($failed), 'Anh khong ton tai phai duoc ghi nhan that bai');
        foreach ($failed as $f) {
            $this->assertNotEmpty($f['failure_reason']);
        }
    }

    public function testAssetStorageTuChoiAnhNgoaiPhamVi(): void
    {
        $userId = $this->createUser('it_ast_' . bin2hex(random_bytes(4)));
        $libraryId = LibraryService::create($userId, 'Lib asset ' . bin2hex(random_bytes(4)));

        $storage = new AssetStorage($this->allowList());

        $result = $storage->saveFromUrl($libraryId, 'https://cdn.example.com/remote.jpg');

        $this->assertFalse($result['success'], 'Anh ngoai pham vi phai bi tu choi');
        $this->assertStringContainsString('không được phép', $result['reason']);
    }

    public function testPhanQuyenDocBanLuuVaAnh(): void
    {
        $owner = $this->createUser('it_perm_o_' . bin2hex(random_bytes(4)));
        $stranger = $this->createUser('it_perm_s_' . bin2hex(random_bytes(4)));
        $viewer = $this->createUser('it_perm_v_' . bin2hex(random_bytes(4)));

        $libraryId = LibraryService::create($owner, 'Lib phan quyen ' . bin2hex(random_bytes(4)));
        AccessControl::addMember($libraryId, $viewer, LibraryRole::Viewer);

        $fetch = (new ArticleFetcher($this->allowList()))
            ->fetch(self::BASE . '/pages/02-bai-viet-co-anh.html');
        $pageId = ArticleService::save($libraryId, $owner, $fetch['final_url'], $fetch['title'],
            '', $fetch['content_html'], $fetch['content_text'], $fetch['excerpt'], null, 'vi', $fetch['layout_used']);
        $snapshotId = SnapshotService::create($pageId, $libraryId);

        // Chu so huu doc duoc.
        $this->assertTrue(AccessControl::canRead($libraryId, $owner));
        // Viewer doc duoc.
        $this->assertTrue(AccessControl::canRead($libraryId, $viewer));
        // Nguoi la khong doc duoc.
        $this->assertFalse(AccessControl::canRead($libraryId, $stranger));

        $this->assertGreaterThan(0, $snapshotId);
    }
}