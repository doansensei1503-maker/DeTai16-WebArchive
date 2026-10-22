<?php
/**
 * Trang doc lai (CN3).
 *
 * DAY LA BAN HTML DOC LAP — khong dung views/layout.php va khong tai
 * /assets/app.css. Ly do: trang nay phai chay duoc hoan toan offline,
 * khong phu thuoc bat ky tai nguyen ben ngoai nao (khong CDN, khong
 * stylesheet dung chung, khong script). Noi dung da duoc SnapshotService
 * lam sach va CSP header khoa la default-src 'none'; script-src 'none'.
 *
 * Vi vay CSS o duoi phai tự chua lai. Hay giu dung voi design token
 * trong public/assets/app.css de hien thi nhat quan.
 */
/** @var string $title */
/** @var array $snapshot */
/** @var array|null $library */
/** @var int $libraryId */

$ic = static function (string $n, int $s = 14): string {
    $p = [
        'arrowL' => '<path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/>',
        'link'   => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'user'   => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'clock'  => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'type'   => '<path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/>',
        'image'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/>',
        'wifiOff'=> '<path d="M1 1l22 22"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.58 9"/><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><path d="M12 20h.01"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" '
        . 'stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . ($p[$n] ?? '') . '</svg>';
};

$backUrl  = $library !== null ? '/libraries/' . $libraryId : '/libraries';
$backName = $library !== null ? $library['name'] : 'Thư viện';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($snapshot['title'] ?: 'Bài đọc lại', ENT_QUOTES, 'UTF-8') ?> · WebArchive</title>
<style>
/* CSS tự chứa — xem ghi chú đầu file. KHông dùng file ngoài. */
*, *::before, *::after { box-sizing: border-box; }

:root {
  --primary: #2f5d9e; --primary-hover: #24487e;
  --border: #dfe3e8; --border-strong: #c4cad1;
  --surface: #fff; --surface-alt: #f9fafc; --bg: #f5f7fa;
  --text: #16191d; --text-secondary: #4d5560; --text-muted: #767f8c;
  --radius-sm: 6px;
  --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
               "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  --font-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas,
               "Liberation Mono", monospace;
}

body {
  margin: 0; background: var(--bg); color: var(--text);
  font-family: var(--font-sans); line-height: 1.7;
  -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;
}

a { color: var(--primary); text-decoration: none; }
a:hover { color: var(--primary-hover); text-decoration: underline; }
:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

/* Thanh dieu huong tren cung */
.topbar {
  background: var(--surface); border-bottom: 1px solid var(--border);
  position: sticky; top: 0; z-index: 10;
}
.topbar .inner {
  max-width: 1180px; margin: 0 auto; padding: 11px 20px;
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  font-size: .875rem;
}
.topbar .back {
  display: inline-flex; align-items: center; gap: 6px;
  color: var(--text-secondary); font-weight: 550;
  padding: 5px 10px 5px 7px; border-radius: var(--radius-sm);
  transition: background .16s, color .16s;
}
.topbar .back:hover { background: var(--surface-alt); color: var(--text); text-decoration: none; }
.topbar .sep { color: var(--border-strong); }
.topbar .ctx { color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.topbar .spacer { flex: 1; }
.topbar .offline {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 3px 10px; border-radius: 999px;
  background: #e6f4ec; color: #1a7f4b; border: 1px solid #b7ddc8;
  font-size: .75rem; font-weight: 600;
}

.read-wrap { max-width: 720px; margin: 0 auto; padding: 36px 20px 80px; }

.read-title {
  font-size: 2rem; font-weight: 700; line-height: 1.25;
  letter-spacing: -.02em; margin: 0 0 14px;
}
.read-info {
  display: flex; flex-wrap: wrap; gap: 8px 18px;
  font-size: .875rem; color: var(--text-muted);
  padding-bottom: 20px; margin-bottom: 28px;
  border-bottom: 1px solid var(--border);
}
.read-info > span { display: inline-flex; align-items: center; gap: 6px; min-width: 0; }
.read-info a { color: var(--text-muted); word-break: break-all; }
.read-info a:hover { color: var(--primary); }

/* Typography noi dung */
.read-body { font-size: 1.0625rem; line-height: 1.78; color: var(--text); }
.read-body > *:first-child { margin-top: 0; }
.read-body h1, .read-body h2, .read-body h3, .read-body h4 {
  margin: 1.7em 0 .55em; line-height: 1.3; font-weight: 650; letter-spacing: -.01em;
}
.read-body h1 { font-size: 1.7em; }
.read-body h2 { font-size: 1.4em; padding-bottom: .3em; border-bottom: 1px solid var(--border); }
.read-body h3 { font-size: 1.18em; }
.read-body h4 { font-size: 1.05em; }
.read-body p { margin: 0 0 1.05em; }
.read-body img {
  max-width: 100%; height: auto; margin: 1.4em 0;
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  background: var(--surface-alt);
}
.read-body a { text-decoration: underline; text-underline-offset: 2px; }
.read-body blockquote {
  border-left: 3px solid var(--primary);
  padding: 2px 0 2px 16px; margin: 1.3em 0;
  color: var(--text-secondary); font-style: italic;
}
.read-body pre {
  background: var(--surface-alt); border: 1px solid var(--border);
  padding: 14px 16px; border-radius: var(--radius-sm);
  overflow-x: auto; margin: 1.3em 0;
  font-family: var(--font-mono); font-size: .875rem; line-height: 1.6;
}
.read-body code {
  background: var(--surface-alt); border: 1px solid var(--border);
  padding: 1px 5px; border-radius: 4px;
  font-family: var(--font-mono); font-size: .875em;
}
.read-body pre code { background: none; border: 0; padding: 0; }
.read-body ul, .read-body ol { margin: 1em 0; padding-left: 26px; }
.read-body li { margin-bottom: 5px; }
.read-body table { border-collapse: collapse; width: 100%; margin: 1.3em 0; font-size: .9375rem; }
.read-body th, .read-body td { border: 1px solid var(--border); padding: 9px 12px; text-align: left; }
.read-body th { background: var(--surface-alt); font-weight: 600; }
.read-body figure { margin: 1.4em 0; }
.read-body figcaption { font-size: .8125rem; color: var(--text-muted); text-align: center; margin-top: 8px; }
.read-body hr { border: 0; border-top: 1px solid var(--border); margin: 2em 0; }

/* Thanh cong cuoi trang */
.read-footer {
  margin-top: 44px; padding-top: 20px; border-top: 1px solid var(--border);
  display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap;
}
.read-footer a {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 8px 14px; border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm); background: var(--surface);
  font-size: .875rem; font-weight: 550; color: var(--text-secondary);
  transition: background .16s, border-color .16s;
}
.read-footer a:hover { background: var(--surface-alt); border-color: var(--text-muted); color: var(--text); text-decoration: none; }

@media (max-width: 600px) {
  .read-wrap { padding: 24px 16px 60px; }
  .read-title { font-size: 1.5rem; }
  .read-body { font-size: 1rem; }
  .read-info { gap: 6px 14px; }
  .topbar .inner { padding: 10px 16px; }
}
@media print {
  .topbar, .read-footer { display: none; }
  body { background: #fff; }
}
</style>
</head>
<body>

<div class="topbar">
  <div class="inner">
    <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="back">
      <?= $ic('arrowL') ?> <?= htmlspecialchars(mb_substr($backName, 0, 26), ENT_QUOTES, 'UTF-8') ?>
    </a>
    <span class="sep" aria-hidden="true">›</span>
    <span class="ctx"><?= htmlspecialchars(mb_substr($snapshot['title'] ?? '', 0, 60), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($snapshot['title'] ?? '') > 60 ? '…' : '' ?></span>
    <span class="spacer"></span>
    <span class="offline"><?= $ic('wifiOff') ?> Bản đọc offline</span>
  </div>
</div>

<main class="read-wrap">
  <h1 class="read-title"><?= htmlspecialchars($snapshot['title'] ?: 'Bài viết không có tiêu đề', ENT_QUOTES, 'UTF-8') ?></h1>

  <div class="read-info">
    <?php if (!empty($snapshot['author'])): ?>
      <span><?= $ic('user') ?> <?= htmlspecialchars($snapshot['author'], ENT_QUOTES, 'UTF-8') ?></span>
    <?php endif; ?>
    <span><?= $ic('clock') ?> Lưu <?= date('d/m/Y H:i', strtotime($snapshot['saved_at'])) ?></span>
    <?php if ((int) $snapshot['word_count'] > 0): ?>
      <span><?= $ic('type') ?> <?= number_format((int) $snapshot['word_count']) ?> từ</span>
    <?php endif; ?>
    <?php if ((int) $snapshot['image_count'] > 0): ?>
      <span><?= $ic('image') ?> <?= (int) $snapshot['image_count'] ?> ảnh</span>
    <?php endif; ?>
    <span><?= $ic('link') ?>
      <a href="<?= htmlspecialchars($snapshot['source_url'], ENT_QUOTES, 'UTF-8') ?>"
         target="_blank" rel="noopener noreferrer">Nguồn gốc</a>
    </span>
  </div>

  <?php /* Noi dung da qua HtmlSanitizer + duoc SnapshotService ghi lai. */ ?>
  <div class="read-body">
    <?= $snapshot['html'] ?>
  </div>

  <div class="read-footer">
    <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>"><?= $ic('arrowL') ?> Về thư viện</a>
    <a href="/pages/<?= (int) ($snapshot['saved_page_id'] ?? 0) ?>">Chi tiết bài viết</a>
  </div>
</main>

</body>
</html>