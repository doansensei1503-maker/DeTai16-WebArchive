<?php
/** @var string $title */
/** @var array<string,string> $errors */
/** @var string $email */
$ic = static function (string $name, int $size = 16): string {
    $paths = [
        'logo'  => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 18.5z"/><path d="M4 10h16"/><path d="M9.5 14h5"/>',
        'mail'  => '<rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="M21.5 6.5L12 13 2.5 6.5"/>',
        'lock'  => '<rect x="4.5" y="11" width="15" height="10" rx="2"/><path d="M8 11V7.5a4 4 0 0 1 8 0V11"/><path d="M12 15.5v3"/>',
        'user'  => '<path d="M19.5 20.5v-1.8a3.6 3.6 0 0 0-3.6-3.6H8.1a3.6 3.6 0 0 0-3.6 3.6v1.8"/><circle cx="12" cy="7.5" r="3.6"/>',
        'alert' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.5h.01"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" '
        . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
};
$old = static function (string $key, ?string $default = '') use ($email): string {
    $val = $_POST[$key] ?? '';
    if ($key === 'email' && $val === '' && $default === '') {
        $val = $email;
    }
    return (string) $val;
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

  <h1>Đăng nhập</h1>
  <p class="lead">Mở khóa thư viện và bài viết đã lưu của bạn.</p>

  <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-error" role="alert">
      <?= $ic('alert', 17) ?><span><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  <?php endif; ?>

  <form method="post" action="/login" novalidate>
    <?= $csrf ?? '' ?>

    <div class="field">
      <label class="field-label" for="email">
        Tên đăng nhập hoặc email
        <?php $idErr = $errors['identifier'] ?? $errors['email'] ?? null; ?>
        <?= $idErr ? '<span class="req" aria-hidden="true">*</span>' : '' ?>
      </label>
      <input id="email" name="identifier" type="text" required autocomplete="username"
             class="input<?= !empty($errors['identifier'] ?? $errors['email'] ?? null) ? ' is-invalid' : '' ?>"
             placeholder="nguyenvana hoặc nguyenvana@example.com"
             value="<?= htmlspecialchars($old('identifier'), ENT_QUOTES, 'UTF-8') ?>">
      <?php if (!empty($errors['identifier'] ?? $errors['email'] ?? null)): ?>
        <span class="field-error"><?= htmlspecialchars(($errors['identifier'] ?? $errors['email']), ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </div>

    <div class="field">
      <label class="field-label" for="password">
        Mật khẩu <?= $errors['password'] ?? null ? '<span class="req" aria-hidden="true">*</span>' : '' ?>
      </label>
      <input id="password" name="password" type="password" required autocomplete="current-password"
             class="input<?= !empty($errors['password']) ? ' is-invalid' : '' ?>"
             placeholder="••••••••">
      <?php if (!empty($errors['password'])): ?>
        <span class="field-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php else: ?>
        <span class="field-hint">Mật khẩu phải có ít nhất 8 ký tự.</span>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary btn-lg btn-block">Đăng nhập</button>
  </form>

  <p class="auth-foot">
    Chưa có tài khoản? <a href="/register">Tạo tài khoản mới</a>
  </p>
</div>