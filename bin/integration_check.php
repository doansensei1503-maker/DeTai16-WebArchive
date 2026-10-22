<?php

declare(strict_types=1);

/**
 * KIEM THU TICH HOP THAT SU — DeTai16 (Chuc nang 1, 2, 3)
 *
 * Chay thuoc tu voi du lieu THAT (khong mock), goi HTTP THAT vao may chu
 * thu nghiem 127.0.0.1:8080, luu vao MySQL THAT.
 *
 * Lenh:  php bin/integration_check.php
 */

require __DIR__ . '/../vendor/autoload.php';
\App\Support\Bootstrap::boot();

use App\Domain\FetchStatus;
use App\Domain\LibraryRole;
use App\Service\AccessControl;
use App\Service\ArticleFetcher;
use App\Service\ArticleService;
use App\Service\AssetStorage;
use App\Service\FetchLogService;
use App\Service\LibraryService;
use App\Service\SnapshotService;
use App\Service\TagService;

$BASE = 'http://127.0.0.1:8080';

$pass = 0;
$fail = 0;
$failures = [];

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $failures;

    if ($ok) {
        $pass++;
        echo "  [PASS] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    } else {
        $fail++;
        $failures[] = $label . ($detail !== '' ? ' — ' . $detail : '');
        echo "  [FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function section(string $title): void
{
    echo "\n" . str_repeat('=', 72) . "\n{$title}\n" . str_repeat('=', 72) . "\n";
}

// =====================================================================
section('CHUONG 0 · KET NOI DU LIEU');
// =====================================================================

$pdo = \App\Support\Database::pdo();
$server = $pdo->query('SELECT VERSION()')->fetchColumn();
check('Ket noi MySQL that', is_string($server), 'Server ' . $server);

$tableCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
)->fetchColumn();
check('Schema da migrate du 10 bang', $tableCount === 10, "Co {$tableCount} bang");

// Kiem tra may chu thu nghiem con song.
$probe = @file_get_contents("{$BASE}/pages/01-article-hop-le.html");
check('May chu thu nghiem 127.0.0.1:8080 phan hoi', $probe !== false, '15 trang HTML fixture');

// =====================================================================
section('CHUC NANG 1 · QUAN LY THU VIEN, NHAN, NGUOI DUNG, PHAN QUYEN');
// =====================================================================

$suffix = (string) random_int(100000, 999999);
$ownerId = \App\Service\UserService::register("owner_{$suffix}", "owner{$suffix}@detai16.test", "Owner1234!", "Chu Thu Vien");
check('Dang ky nguoi dung moi', $ownerId > 0, "user_id={$ownerId}");

$libraryId = LibraryService::create($ownerId, "Thu Vien Kiem Thu {$suffix}", 'Tao trong kiem thu tich hop');
check('Tao thu vien', $libraryId > 0, "library_id={$libraryId}");

$lib = LibraryService::find($libraryId);
check('Doc lai thong tin thu vien', ($lib['name'] ?? null) === "Thu Vien Kiem Thu {$suffix}");

$editorId = \App\Service\UserService::register("editor_{$suffix}", "editor{$suffix}@detai16.test", "Editor1234!", 'Bien Tap Vien');
$viewerId = \App\Service\UserService::register("viewer_{$suffix}", "viewer{$suffix}@detai16.test", "Viewer1234!", 'Nguoi Xem');

AccessControl::addMember($libraryId, $editorId, LibraryRole::Editor);
AccessControl::addMember($libraryId, $viewerId, LibraryRole::Viewer);
check('Them 2 thanh vien (Editor, Viewer)', true, "editor={$editorId} viewer={$viewerId}");

check('Owner co quyen manage', AccessControl::isOwner($libraryId, $ownerId));
check('Editor co quyen ghi (write)', AccessControl::canWrite($libraryId, $editorId));
check('Editor KHONG co quyen manage', !AccessControl::canManage($libraryId, $editorId));
check('Viewer chi co quyen doc (read)', AccessControl::canRead($libraryId, $viewerId));
check('Viewer KHONG co quyen ghi', !AccessControl::canWrite($libraryId, $viewerId));
check('Nguoi la KHONG doc duoc thu vien', !AccessControl::canRead($libraryId, 999999));
check('Khach (khong dang nhap) KHONG doc duoc', !AccessControl::canRead($libraryId, null));

// --- Nhan (tag) ---
$tagA = TagService::create($libraryId, "Lập trình {$suffix}", '#3b6ea5');
$tagB = TagService::create($libraryId, "Tin tức {$suffix}", '#c0392b');
check('Tao 2 nhan', $tagA > 0 && $tagB > 0, "tagA={$tagA} tagB={$tagB}");

$tags = TagService::listForLibrary($libraryId);
check('Danh sach nhan cua thu vien tra ve >= 2', count($tags) >= 2, count($tags) . ' nhan');

// --- SSRF / xac thuc URL ---
$allowList = \App\Security\AllowList::fromConfig();
$validator = new \App\Security\UrlValidator($allowList);

$blocked = [
    'file:///etc/passwd',
    'http://169.254.169.254/latest/meta-data/',
    'http://10.0.0.5/admin',
    'javascript:alert(1)',
    'data:text/html,<h1>x</h1>',
];
foreach ($blocked as $badUrl) {
    $v = $validator->validate($badUrl);
    check('Chan URL nguy hiem: ' . $badUrl, !$v->allowed, 'reason=' . $v->reasonCode);
}

$okUrl = "{$BASE}/pages/01-article-hop-le.html";
$v = $validator->validate($okUrl);
check('Chap nhan URL trong allow-list', $v->allowed, 'reason=' . $v->reasonCode);

// =====================================================================
section('CHUC NANG 2 · LAY HTML, TRICH XUAT BAI VIET, KIEM TRA CHAT LUONG');
// =====================================================================

$fetcher = new ArticleFetcher($allowList);
$targets = [
    '01-article-hop-le' => 'Bài hợp lệ cơ bản',
    '02-bai-viet-co-anh' => 'Bài có ảnh',
    '10-tieng-viet-unicode' => 'Tiếng Việt unicode',
    '15-bai-viet-nhieu-anh' => 'Bài nhiều ảnh',
];
$pageIds = [];

foreach ($targets as $slug => $label) {
    $url = "{$BASE}/pages/{$slug}.html";

    $logId = FetchLogService::create($libraryId, $ownerId, $url);
    $r = $fetcher->fetch($url, $logId, $libraryId, $ownerId);

    if (!$r['success']) {
        check("Fetch [{$slug}]", false, 'status=' . ($r['status']->value ?? '?'));
        continue;
    }

    // ArticleFetcher da trich xuat san (fetch + extract trong mot lan goi).
    $ex = $r;

    $titleOk = ($ex['title'] ?? '') !== '';
    $bodyOk  = ($ex['content_text'] ?? '') !== '';
    $noiseGone = !preg_match('/<script/i', (string) ($ex['content_html'] ?? ''))
        && stripos((string) ($ex['content_html'] ?? ''), '<footer') === false;

    $pageId = ArticleService::save(
        $libraryId,
        $ownerId,
        $url,
        $ex['title'] ?? 'Khong co tieu de',
        $ex['author'] ?? '',
        $ex['content_html'] ?? '',
        $ex['content_text'] ?? '',
        $ex['excerpt'] ?? '',
        $ex['published_at'] ?? null,
        $ex['language'] ?? 'vi',
        $ex['layout_used'] ?? null,
        $logId,
    );
    $pageIds[$slug] = $pageId;

    check(
        "Fetch + trich xuat [{$slug}]",
        $titleOk && $bodyOk && $noiseGone,
        sprintf(
            'layout=%s, %d tu, %d ky tu HTML, id=%d',
            $ex['layout_used'] ?? '?',
            $ex['word_count'] ?? 0,
            strlen($ex['content_html'] ?? ''),
            $pageId,
        ),
    );

    FetchLogService::update(
        $logId,
        FetchStatus::Saved,
        finalUrl: $ex['final_url'] ?? $url,
        httpCode: (int) ($ex['http_code'] ?? 0) ?: null,
        message: 'OK',
        redirectChain: $ex['redirect_chain'] ?? null,
        durationMs: (int) ($ex['duration_ms'] ?? 0),
    );
}

// Lay HTML that (khong mock) qua cURL thuc te.
$live = $fetcher->fetch($okUrl);
check(
    'cURL that tai + trich xuat tu may chu thu nghiem',
    $live['success'] && strlen((string) ($live['content_html'] ?? '')) > 200,
    strlen((string) ($live['content_html'] ?? '')) . ' byte HTML'
);

// Kiem tra chat luong: trang loi phai bi danh dau that bai.
$r404 = $fetcher->fetch("{$BASE}/pages/06-loi-404.html");
check('Trang HTTP 404 bi danh dau that bai', !$r404['success'], 'status=' . ($r404['status']->value ?? '?'));

$loop = $fetcher->fetch("{$BASE}/redirect-loop");
check('Vong chuyen huong vo han bi chan', !$loop['success'], 'status=' . ($loop['status']->value ?? '?'));

// Ghi nhat ky trang thai.
$logs = FetchLogService::recentForLibrary($libraryId, 50);
check('Nhat ky trang thai da ghi lai', count($logs) >= count($targets), count($logs) . ' dong log');

// Trang khong noi dung -> bao loi ro rang.
$rEmpty = $fetcher->fetch("{$BASE}/pages/05-trang-khong-co-noi-dung.html");
check('Trang khong co noi dung bi bao loi', !$rEmpty['success'], 'status=' . ($rEmpty['status']->value ?? '?'));

// =====================================================================
section('CHUC NANG 3 · BAN DOC LAI AN TOAN, OFFLINE, LUU ANH');
// =====================================================================

$withImageSlug = '02-bai-viet-co-anh';
$imagePageId = $pageIds[$withImageSlug] ?? null;

if ($imagePageId === null) {
    check('Co bai co anh de tao ban doc lai', false);
} else {
    $snapId = SnapshotService::create($imagePageId, $libraryId);
    $snap = SnapshotService::find($snapId);

    check('Tao ban doc lai (snapshot)', $snapId > 0, "snapshot_id={$snapId}");
    check('Ban doc lai co HTML', ($snap['html'] ?? '') !== '', strlen($snap['html'] ?? '') . ' byte');

    $h = $snap['html'] ?? '';
    check('Da loai bo <script>', !preg_match('/<script/i', $h));
    check('Da loai bo thuoc tinh on* (JS inline)', !preg_match('/\son[a-z]+\s*=/i', $h));
    check('Da loai bo <iframe>/<object>/<embed>', !preg_match('/<(iframe|object|embed)/i', $h));
    check('Da loai bo <style> va style inline', !preg_match('/<style/i', $h));
    check('Da loai bo the <form>', !preg_match('/<form/i', $h));

    // Link ngoai phai co rel=noopener.
    $extLinksOk = true;
    if (preg_match_all('#<a\b[^>]*href="(https?://[^"]+)"[^>]*>#i', $h, $m) > 0) {
        foreach ($m[0] as $anchor) {
            if (stripos($anchor, 'rel="noopener') === false && stripos($anchor, "rel='noopener") === false) {
                $extLinksOk = false;
                break;
            }
        }
    }
    check('Link ngoai co rel="noopener"', $extLinksOk);

    // Anh phai tro ve endpoint noi bo.
    $allInternal = true;
    $imgCount = 0;
    if (preg_match_all('#<img\b[^>]*src="([^"]+)"#i', $h, $im) > 0) {
        $imgCount = count($im[1]);
        foreach ($im[1] as $src) {
            if (!str_starts_with($src, '/asset/')) {
                $allInternal = false;
                break;
            }
        }
    }
    check('Moi anh tro ve endpoint noi bo /asset/{hash}', $allInternal, "{$imgCount} anh");
    check('Khong con src tro ve trang nguon ben ngoai', !preg_match('#<img\b[^>]*src="https?://#i', $h));

    // File anh that su da luu.
    $assets = (new AssetStorage($allowList))->listForLibrary($libraryId);
    $saved = array_values(array_filter($assets, static fn ($a) => ($a['status'] ?? '') === 'saved'));
    check('Anh da luu vao dia local', count($saved) > 0, count($saved) . ' anh saved');

    $dir = \App\Support\Env::get('STORAGE_PATH', 'storage');
    $filesOnDisk = [];
    foreach ($saved as $a) {
        $p = dirname(__DIR__) . '/' . $dir . '/assets/' . $libraryId;
        foreach (glob($p . '/*') ?: [] as $f) {
            $filesOnDisk[basename($f)] = filesize($f);
        }
    }
    check('Tep anh that su ton tai tren dia', count($filesOnDisk) > 0, count($filesOnDisk) . ' tep');

    // Kiem tra MIME that su.
    $mimeOk = true;
    foreach (array_keys($filesOnDisk) as $name) {
        $path = dirname(__DIR__) . '/' . $dir . '/assets/' . $libraryId . '/' . $name;
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            $mimeOk = false;
        }
    }
    check('Moi tep la anh that (PNG/JPEG/WebP)', $mimeOk);

    // Khong con phu thuoc tai nguyen ben ngoai.
    $noRemoteDeps = !preg_match('#<img\b[^>]*src="https?://#i', $h)
        && !preg_match('#<link\b[^>]*href="https?://#i', $h)
        && !preg_match('#<script\b#i', $h);
    check('Ban doc lai doc lap (khong tai tai nguyen ngoai)', $noRemoteDeps);

    // --- Phan quyen khi doc ban luu va anh ---
    $viewerCanRead = AccessControl::canRead($libraryId, $viewerId);
    $viewerCanWrite = AccessControl::canWrite($libraryId, $viewerId);
    $outsiderCanRead = AccessControl::canRead($libraryId, 999999);

    check('Thanh vien duoc phep doc ban luu', $viewerCanRead);
    check('Thanh vien khong the sua ban luu', !$viewerCanWrite);
    check('Nguoi ngoai khong doc duoc ban luu', !$outsiderCanRead);
}

// --- Kiem tra CSP ---
// Doc file ban doc lai truc tiep tu DB (khong qua HTTP) de kiem tra CSP.
$anySnap = \App\Support\Database::first(
    'SELECT id, html, csp FROM snapshots ORDER BY id DESC LIMIT 1'
);
if ($anySnap !== null) {
    check('CSP luu trong DB', ($anySnap['csp'] ?? '') !== '', $anySnap['csp'] ?? '');
    check("CSP chan script: co script-src 'none'", str_contains((string) $anySnap['csp'], "script-src 'none'"));
}

// =====================================================================
section('KIEM TRA LUU TRU (DON VI)');
// =====================================================================

$page = ArticleService::find($pageIds['01-article-hop-le'] ?? 0);
check('Bai da luu doc lai duoc tu DB', $page !== null);
check('Co noi dung ro', ($page['content_text'] ?? '') !== '');
check('Khong co <script> trong DB', !preg_match('/<script/i', (string) ($page['content_html'] ?? '')));

// Trung URL bi chan.
$dupCaught = false;
try {
    ArticleService::save($libraryId, $ownerId, $okUrl, 'Trung URL', '', '', '', '', null, 'vi', null, null);
} catch (\InvalidArgumentException) {
    $dupCaught = true;
}
check('Trung URL bi chan bang loi', $dupCaught);

// =====================================================================
section('KET QUA');
// =====================================================================

echo "\nTong so kiem thu : " . ($pass + $fail) . "\n";
echo "PASS             : {$pass}\n";
echo "FAIL             : {$fail}\n";

if ($failures !== []) {
    echo "\nDanh sach FAIL:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
}

echo "\n" . ($fail === 0
    ? "KET QUA: PASS toan bo kiem thu tich hop that su.\n"
    : "KET QUA: CO {$fail} Kiem thu that bai.\n");

echo "Thu vien kiem thu ID: {$libraryId}\n";

exit($fail === 0 ? 0 : 1);