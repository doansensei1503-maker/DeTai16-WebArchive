<?php

declare(strict_types=1);

/**
 * ROUTER cho may chu thu nghiem DeTai16.
 *
 * Chay: php -S 127.0.0.1:8080 -t test_server test_server/router.php
 *
 * Cung cap 15 trang HTML thu nghiem + cac endpoint dac biet de kiem thu
 * trang thai HTTP, timeout va chuyen huong.
 */

$root = __DIR__;
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$uri  = rtrim($uri, '/') ?: '/';

// ---------------------------------------------------------------------
// Trang loi 404 that (tra ve HTTP status 404 that su)
// ---------------------------------------------------------------------
if ($uri === '/pages/06-loi-404.html' || $uri === '/loi-404') {
    http_response_code(404);
    $file = $root . '/pages/06-loi-404.html';
    if (is_file($file)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($file);
        exit;
    }
}

// Trang 404 mac dinh cua may chu thu nghiem.
// CHI ap dung khi file that su khong ton tai — neu khong, guard nay se
// chan dap tat ca 15 trang HTML thu nghiem.
if (str_starts_with($uri, '/pages/')) {
    if (!is_file($root . $uri)) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><title>404 - Không tìm thấy</title></head>
<body><h1>404</h1><p>Khong tim thay trang: {$uri}</p></body></html>
HTML;
        exit;
    }
}

if ($uri === '/khong-ton-tai') {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><title>404 - Không tìm thấy</title></head>
<body><h1>404</h1><p>Trang khong ton tai.</p></body></html>
HTML;
    exit;
}

// ---------------------------------------------------------------------
// Trang chuyen huong ra ngoai (test SSRF) - chuyen sang domain bi cam
// ---------------------------------------------------------------------
if ($uri === '/redirect-ra-ngoai') {
    header('Location: http://example.com/khong-cho-phep', true, 302);
    exit;
}

// Chuyen huong hop le sang trang bai viet.
if ($uri === '/redirect-hop-le') {
    header('Location: /pages/01-article-hop-le.html', true, 302);
    exit;
}

// Vong chuyen huong vo han (test gioi han so lan chuyen huong).
if ($uri === '/redirect-loop') {
    header('Location: /redirect-loop', true, 302);
    exit;
}

// ---------------------------------------------------------------------
// Endpoint tro loi HTTP cu the (test phan biet trang thai)
// ---------------------------------------------------------------------
if (preg_match('#^/http-status/(\d{3})$#', $uri, $m) === 1) {
    http_response_code((int) $m[1]);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="vi"><head><title>Loi ' . $m[1] . '</title></head><body><p>Response code: ' . $m[1] . '</p></body></html>';
    exit;
}

// Endpoint tre cham (test timeout) - tre 60 giay.
if ($uri === '/tre-cham') {
    sleep(60);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body><article><h1>Trang tre cham</h1><p>Noi dung sau khi tre lau.</p></article></body></html>';
    exit;
}

// ---------------------------------------------------------------------
// Phuc vu anh (dung de thu nghiem luu anh)
// ---------------------------------------------------------------------
if (str_starts_with($uri, '/images/')) {
    serve_image($root . '/images' . substr($uri, 7), $uri);
}

// ---------------------------------------------------------------------
// Phuc vu trang HTML tinh
// ---------------------------------------------------------------------
$file = $root . $uri;
if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    readfile($file);
    exit;
}

// Trang chu may chu thu nghiem.
if ($uri === '/' || $uri === '') {
    header('Content-Type: text/html; charset=utf-8');
    echo build_index($root);
    exit;
}

// Khong tim thay.
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>404</title></head><body><h1>404</h1><p>Khong tim thay.</p></body></html>';

// =====================================================================
// HAM PHU
// =====================================================================

function serve_image(string $path, string $uri): never
{
    if (!is_file($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo '404 - Khong tim thay anh';
        exit;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'png'  => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        default => 'application/octet-stream',
    };

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

function build_index(string $root): string
{
    $files = glob($root . '/pages/*.html') ?: [];
    sort($files);

    $rows = '';
    foreach ($files as $file) {
        $name = basename($file);
        $url  = '/pages/' . $name;
        $title = extract_title($file);
        $rows .= '<tr><td><a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($title) . '</a></td>'
            . '<td><code>' . htmlspecialchars($url) . '</code></td></tr>';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><title>Máy chủ thử nghiệm DeTai16</title>
<style>
body{font-family:-apple-system,'Segoe UI',sans-serif;max-width:900px;margin:40px auto;padding:0 16px;line-height:1.6}
h1{color:#3b6ea5}table{border-collapse:collapse;width:100%;margin-top:16px}
th,td{border:1px solid #ddd;padding:8px 12px;text-align:left}
th{background:#f4f4f4}code{font-size:0.85rem;background:#f8f8f8;padding:2px 5px}
section{margin-top:32px;padding-top:16px;border-top:1px solid #ddd}
</style></head><body>
<h1>Máy chủ thử nghiệm DeTai16</h1>
<p>Đây là máy chủ HTML tĩnh công khai dùng để kiểm thử ứng dụng DeTai16.</p>

<h2>Các trang HTML thử nghiệm (15 trang)</h2>
<table>
<tr><th>Tiêu đề</th><th>Đường dẫn</th></tr>
{$rows}
</table>

<section>
<h2>Endpoint đặc biệt (kiểm thử trạng thái)</h2>
<ul>
<li><code>/http-status/500</code> — Lỗi máy chủ</li>
<li><code>/http-status/503</code> — Dịch vụ không khả dụng</li>
<li><code>/redirect-hop-le</code> — Chuyển hướng hợp lệ → bài viết</li>
<li><code>/redirect-ra-ngoai</code> — Chuyển hướng ra ngoài phạm vi (phải bị chặn)</li>
<li><code>/redirect-loop</code> — Vòng chuyển hướng vô hạn (phải bị chặn)</li>
<li><code>/tre-cham</code> — Trang tre 60s (test timeout)</li>
<li><code>/pages/06-loi-404.html</code> — Trả về HTTP 404 thật</li>
</ul>
</section>
</body></html>
HTML;
}

function extract_title(string $file): string
{
    $content = @file_get_contents($file);
    if ($content === false) {
        return basename($file);
    }

    if (preg_match('#<title>(.*?)</title>#is', $content, $m) === 1) {
        return trim($m[1]);
    }

    if (preg_match('#<h1[^>]*>(.*?)</h1>#is', $content, $m) === 1) {
        return trim(strip_tags($m[1]));
    }

    return basename($file);
}