<?php
/**
 * Layout dung chung cho toan trang (tru trang doc lai offline
 * views/read/show.php la ban HTML doc lap de khong tai tai nguyen ngoai).
 *
 * @var string      $title
 * @var string      $csrf        HTML <input type=hidden> cua CSRF
 * @var string      $content     Noi dung da render cua template con
 * @var \App\Domain\User|null $user
 * @var string      $currentPath
 */

/**
 * Icon SVG noi bo — moi trang tu khai bao danh sach nay.
 * Khong dung emoji va khong tai icon tu CDN.
 *
 * @param list<string> $names
 */
$icon = static function (array $names, int $size = 16): string {
    $paths = [
        // Logo: trang luu -> moc trang, bao hinh "archive"
        'logo'      => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 18.5z"/><path d="M4 10h16"/><path d="M9.5 14h5"/>',
        'book'      => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'library'   => '<path d="M4 4v16"/><path d="M8 8v12"/><path d="M12 6v14"/><path d="M16 8v12"/><path d="M20 5v15"/>',
        'file'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h4"/>',
        'link'      => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'type'      => '<path d="M4 7V4.5h16V7"/><path d="M9.5 20h5"/><path d="M12 4.5V20"/>',
        'tag'       => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><path d="M7 7h.01"/>',
        'image'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="M21 15.5l-4.5-4.5L6 21"/>',
        'users'     => '<path d="M16 21v-1.8a3.6 3.6 0 0 0-3.6-3.6H6.6A3.6 3.6 0 0 0 3 19.2V21"/><circle cx="9.5" cy="7.5" r="3.6"/><path d="M21 21v-1.8a3.6 3.6 0 0 0-2.7-3.5"/><path d="M15.5 4.2a3.6 3.6 0 0 1 0 6.9"/>',
        'user'      => '<path d="M19.5 20.5v-1.8a3.6 3.6 0 0 0-3.6-3.6H8.1a3.6 3.6 0 0 0-3.6 3.6v1.8"/><circle cx="12" cy="7.5" r="3.6"/>',
        'mail'      => '<rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="M21.5 6.5L12 13 2.5 6.5"/>',
        'plus'      => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'menu'      => '<path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/>',
        'arrowLeft' => '<path d="M19 12H5"/><path d="M11.5 18.5L5 12l6.5-6.5"/>',
        'save'      => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'shield'    => '<path d="M12 22s8-4 8-10V5.5L12 2.5 4 5.5V12c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
        'check'     => '<path d="M20 6L9 17l-5-5"/>',
        'alert'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.5h.01"/>',
        'trash'     => '<path d="M3.5 6h17"/><path d="M8.5 6V4.2a1.2 1.2 0 0 1 1.2-1.2h4.6a1.2 1.2 0 0 1 1.2 1.2V6"/><path d="M18.5 6v13a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2V6"/><path d="M10 11v5.5"/><path d="M14 11v5.5"/>',
        'close'     => '<path d="M18 6L6 18"/><path d="M6 6l12 12"/>',
        'wifiOff'   => '<path d="M1 1l22 22"/><path d="M16.7 11.1A10.9 10.9 0 0 1 19 12.5"/><path d="M5 12.5a10.9 10.9 0 0 1 5.2-2.4"/><path d="M10.7 5a16 16 0 0 1 11.9 4"/><path d="M1.4 9a15.9 15.9 0 0 1 4.7-2.9"/><path d="M8.5 16.1a6 6 0 0 1 7 0"/><path d="M12 20h.01"/>',
        'search'    => '<circle cx="11" cy="11" r="7.5"/><path d="M21 21l-4.3-4.3"/>',
        'eyeOff'    => '<path d="M9.9 5.2A9.8 9.8 0 0 1 12 5c6 0 10 7 10 7a18 18 0 0 1-2.6 3.7"/><path d="M6.3 6.8A18 18 0 0 0 2 12s4 7 10 7a9.7 9.7 0 0 0 4.3-1"/><path d="M2 2l20 20"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
    ];

    $body = '';
    foreach ($names as $n) {
        $body .= $paths[$n] ?? '';
    }

    if ($body === '') {
        return '';
    }

    $stroke = ($names === ['logo']) ? 1.9 : 1.8;

    return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" '
        . 'stroke="currentColor" stroke-width="' . $stroke . '" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
};

$pageTitle = isset($title) && $title !== '' ? $title . ' · WebArchive' : 'WebArchive — Thư viện Web cá nhân';
$displayName = $user !== null ? ($user->displayName ?: $user->username) : '';
$isLibraries = $currentPath === '/libraries' || str_starts_with($currentPath, '/libraries/');

$flashSuccess = (string) ($_SESSION['_success'] ?? '');
unset($_SESSION['_success']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<meta name="description" content="WebArchive — lưu trang Web để đọc lại và tìm kiếm. Bản đọc chạy độc lập, không phụ thuộc trang gốc.">
<meta name="color-scheme" content="light">
<link rel="stylesheet" href="/assets/app.css">
</head>
<body>

<nav class="nav" aria-label="Điều hướng chính">
  <div class="container">
    <a href="/" class="logo nav-brand">
      <span class="logo-mark"><?= $icon(['logo'], 17) ?></span>
      <span class="logo-text">
        <span class="logo-name">WebArchive</span>
        <span class="logo-sub">Thư viện Web cá nhân</span>
      </span>
    </a>

    <?php if ($user !== null): ?>
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-menu"
              aria-label="Mở menu"
              onclick="var m=document.getElementById('main-menu');
                       var open=m.classList.toggle('is-open');
                       this.setAttribute('aria-expanded', open?'true':'false');
                       this.setAttribute('aria-label', open?'Đóng menu':'Mở menu');">
        <?= $icon(['menu'], 20) ?>
      </button>

      <div class="nav-menu" id="main-menu">
        <nav class="nav-links">
          <a href="/libraries" class="nav-link<?= $isLibraries ? ' is-active' : '' ?>"
             <?= $isLibraries ? 'aria-current="page"' : '' ?>>Thư viện</a>
        </nav>
        <span class="nav-divider" aria-hidden="true"></span>
        <span class="nav-user">
          <?= $icon(['user'], 14) ?><span class="truncate"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
        </span>
        <a href="/logout" class="btn btn-outline btn-sm">Đăng xuất</a>
      </div>
    <?php endif; ?>
  </div>
</nav>

<?php if ($user === null): ?>
  <div class="auth">
<?php endif; ?>

<div class="app-main">
  <div class="container">
<?php if ($flashSuccess !== ''): ?>
    <div class="alert alert-ok" role="status">
      <?= $icon(['check'], 17) ?><span><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<?= $content ?? '' ?>
  </div>
</div>

<?php if ($user === null): ?>
  </div>
<?php endif; ?>

</body>
</html>