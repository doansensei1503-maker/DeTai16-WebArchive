<?php
/** @var string $title */
/** @var array<string,string> $errors */
$ic = static function (string $name, int $size = 16): string {
    $paths = [
        'logo'  => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 18.5z"/><path d="M4 10h16"/><path d="M9.5 14h5"/>',
        'mail'  => '<rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="M21.5 6.5L12 13 2.5 6.5"/>',
        'lock'  => '<rect x="4.5" y="11" width="15" height="10" rx="2"/><path d="M8 11V7.5a4 4 0 0 1 8 0V11"/><path d="M12 15.5v3"/>',
        'user'  => '<path d="M19.5 20.5v-1.8a3.6 3.6 0 0 0-3.6-3.6H8.1a3.6 3.6 0 0 0-3.6 3.6v1.8"/><circle cx="12" cy="7.5" r="3.6"/>',
        'alert' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.5h.01"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" '
        . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
};
?>
<div class="auth-card">
  <div class="logo auth-brand">
    <span class="logo-mark"><?= $ic('logo', 17) ?></span>
    <span class="logo-text">
      <span class="logo-name">WebArchive</span>
      <span class="logo-sub">Thư viện Web cá nhân</span>
    </span>
  </div>

  <h1>Tạo tài khoản</h1>
  <p class="lead">Bắt đầu lưu giữ trang Web bạn muốn đọc lại sau.</p>

  <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-error" role="alert">
      <?= $ic('alert', 17) ?><span><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  <?php endif; ?>

  <form method="post" action="/register" novalidate>
    <?= $csrf ?? '' ?>

    <div class="field">
      <label class="field-label" for="username">Tên đăng nhập</label>
      <input id="username" name="username" type="text" required minlength="3" maxlength="60"
             autocomplete="username"
             class="input<?= !empty($errors['username']) ? ' is-invalid' : '' ?>"
             placeholder="nguyenvana"
             value="<?= htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <?php if (!empty($errors['username'])): ?>
        <span class="field-error"><?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php else: ?>
        <span class="field-hint">3 – 60 ký tự. Có thể hiển thị lên như tên gọi.</span>
      <?php endif; ?>
    </div>

    <div class="field">
      <label class="field-label" for="email">Email</label>
      <input id="email" name="email" type="email" required autocomplete="email"
             class="input<?= !empty($errors['email']) ? ' is-invalid' : '' ?>"
             placeholder="nguyenvana@example.com"
             value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <?php if (!empty($errors['email'])): ?>
        <span class="field-error"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php else: ?>
        <span class="field-hint">Dùng để thông báo khi bài viết sẵn sàng.</span>
      <?php endif; ?>
    </div>

    <div class="field">
      <label class="field-label" for="password">Mật khẩu</label>
      <input id="password" name="password" type="password" required minlength="8"
             autocomplete="new-password"
             class="input<?= !empty($errors['password']) ? ' is-invalid' : '' ?>"
             placeholder="Ít nhất 8 ký tự">
      <?php if (!empty($errors['password'])): ?>
        <span class="field-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php else: ?>
        <span class="field-hint">Tối thiểu 8 ký tự. Nên trộn chữ và số.</span>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-accent btn-lg btn-block">Tạo tài khoản</button>
  </form>

  <p class="auth-foot">
    Đã có tài khoản? <a href="/login">Đăng nhập</a>
  </p>
</div>