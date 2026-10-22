<?php
/** @var string $title */
/** @var array $library */
/** @var array $members */
/** @var string $csrf */
/** @var array<string,string> $errors */

$ic = static function (array $names, int $s = 16): string {
    $paths = [
        'arrowLeft' => '<path d="M19 12H5"/><path d="M11.5 18.5L5 12l6.5-6.5"/>',
        'library'   => '<path d="M4 4v16"/><path d="M8 8v12"/><path d="M12 6v14"/><path d="M16 8v12"/><path d="M20 5v15"/>',
        'users'     => '<path d="M16 21v-1.8a3.6 3.6 0 0 0-3.6-3.6H6.6A3.6 3.6 0 0 0 3 19.2V21"/><circle cx="9.5" cy="7.5" r="3.6"/><path d="M21 21v-1.8a3.6 3.6 0 0 0-2.7-3.5"/><path d="M15.5 4.2a3.6 3.6 0 0 1 0 6.9"/>',
        'plus'      => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'user'      => '<path d="M19.5 20.5v-1.8a3.6 3.6 0 0 0-3.6-3.6H8.1a3.6 3.6 0 0 0-3.6 3.6v1.8"/><circle cx="12" cy="7.5" r="3.6"/>',
        'mail'      => '<rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="M21.5 6.5L12 13 2.5 6.5"/>',
        'alert'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.5h.01"/>',
        'close'     => '<path d="M18 6L6 18"/><path d="M6 6l12 12"/>',
        'trash'     => '<path d="M3.5 6h17"/><path d="M8.5 6V4.2a1.2 1.2 0 0 1 1.2-1.2h4.6a1.2 1.2 0 0 1 1.2 1.2V6"/><path d="M18.5 6v13a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2V6"/>',
    ];
    $body = '';
    foreach ($names as $n) { $body .= $paths[$n] ?? ''; }
    return '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $body . '</svg>';
};

$roleMap = [
    'owner'  => ['Chủ sở hữu',   'badge-info',  'Toàn quyền: sửa, xóa và quản lý thành viên'],
    'editor' => ['Biên tập viên', 'badge-teal',  'Có thể thêm bài viết và gắn nhãn'],
    'viewer' => ['Người xem',     'badge-muted', 'Chỉ xem, không thay đổi nội dung'],
];
?>
<nav class="crumbs" aria-label="Đường dẫn">
  <a href="/libraries"><?= $ic(['library'], 13) ?> Thư viện</a>
  <span class="sep" aria-hidden="true">›</span>
  <a href="/libraries/<?= (int) $library['id'] ?>"><?= htmlspecialchars((string) $library['name'], ENT_QUOTES, 'UTF-8') ?></a>
  <span class="sep" aria-hidden="true">›</span>
  <span aria-current="page">Thành viên</span>
</nav>

<div class="page-head">
  <div>
    <span class="eyebrow">Phân quyền</span>
    <h1>Thành viên &amp; phân quyền</h1>
    <p>Quản lý quyền truy cập thư viện <b><?= htmlspecialchars((string) $library['name'], ENT_QUOTES, 'UTF-8') ?></b>.</p>
  </div>
  <div class="page-head-actions">
    <button type="button" class="btn btn-primary"
            aria-expanded="false" aria-controls="add-form"
            onclick="
              var f=document.getElementById('add-form');
              var open=f.classList.toggle('is-open');
              this.setAttribute('aria-expanded', open ? 'true' : 'false');
              if (open) { document.getElementById('username').focus(); }">
      <?= $ic(['plus']) ?> Thêm thành viên
    </button>
  </div>
</div>

<?php if (!empty($errors['general'])): ?>
  <div class="alert alert-error" role="alert">
    <?= $ic(['alert'], 18) ?><span><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></span>
  </div>
<?php endif; ?>

<!-- Form them thanh vien -->
<div id="add-form" class="card hidden">
  <div class="card-head"><h2>Thêm thành viên</h2></div>
  <div class="card-body">
    <form method="POST" action="/libraries/<?= (int) $library['id'] ?>/members" novalidate>
      <?= $csrf ?>

      <div style="display:grid;gap:var(--s-4);grid-template-columns:2fr 1fr auto;align-items:flex-end">
        <div class="field" style="margin:0">
          <label class="field-label" for="username">Tên đăng nhập hoặc Email <span class="req">*</span></label>
          <input type="text" id="username" name="username"
                 class="input<?= !empty($errors['username']) ? ' is-invalid' : '' ?>"
                 required autofocus placeholder="Nhập username hoặc email…">
        </div>

        <div class="field" style="margin:0">
          <label class="field-label" for="role">Vai trò</label>
          <select id="role" name="role" class="input">
            <option value="viewer">Người xem — chỉ đọc</option>
            <option value="editor">Biên tập viên — thêm &amp; gắn nhãn</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary">Thêm</button>
      </div>

      <?php if (!empty($errors['username'])): ?>
        <span class="field-error" style="margin-top:8px;display:block"><?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>Danh sách thành viên</h2>
    <span class="hint"><b class="tnum"><?= count($members) ?></b> người</span>
  </div>

  <?php if (empty($members)): ?>
    <div class="empty">
      <div class="empty-icon"><?= $ic(['users'], 27) ?></div>
      <h3>Chưa có thành viên nào</h3>
      <p>Thêm thành viên để phối hợp thu thập và gắn nhãn bài viết trong thư viện này.</p>
    </div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Thành viên</th>
          <th>Email</th>
          <th>Vai trò</th>
          <th class="col-actions">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
          <?php [$rLabel, $rClass, $rDesc] = $roleMap[$m['role']] ?? ['Thành viên', 'badge-muted', '']; ?>
          <tr>
            <td data-label="Thành viên">
              <div class="flex gap-2" style="align-items:center">
                <span style="color:var(--text-muted);display:inline-flex"><?= $ic(['user'], 18) ?></span>
                <div>
                  <strong><?= htmlspecialchars((string) $m['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                  <?php if (!empty($m['display_name'])): ?>
                    <span class="dim"> · <?= htmlspecialchars((string) $m['display_name'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </td>

            <td data-label="Email">
              <span class="dim flex gap-2" style="align-items:center">
                <?= $ic(['mail'], 14) ?><?= htmlspecialchars((string) $m['email'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>

            <td data-label="Vai trò">
              <span class="badge <?= $rClass ?>" title="<?= htmlspecialchars($rDesc, ENT_QUOTES, 'UTF-8') ?>"><?= $rLabel ?></span>
            </td>

            <td class="col-actions" data-label="Thao tác">
              <?php if ($m['role'] !== 'owner'): ?>
                <div class="flex gap-2" style="justify-content:flex-end">
                  <form method="POST"
                        action="/libraries/<?= (int) $library['id'] ?>/members/<?= (int) $m['user_id'] ?>?_method=PUT"
                        class="flex">
                    <?= $csrf ?>
                    <label class="sr-only" for="role-<?= (int) $m['user_id'] ?>">
                      Đổi vai trò của <?= htmlspecialchars((string) $m['username'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <select name="role" id="role-<?= (int) $m['user_id'] ?>"
                            class="input" onchange="if(confirm('Đổi vai trò của người dùng này?')) this.form.submit();">
                      <option value="viewer" <?= $m['role'] === 'viewer' ? 'selected' : '' ?>>Người xem</option>
                      <option value="editor" <?= $m['role'] === 'editor' ? 'selected' : '' ?>>Biên tập viên</option>
                    </select>
                  </form>

                  <form method="POST"
                        action="/libraries/<?= (int) $library['id'] ?>/members/<?= (int) $m['user_id'] ?>?_method=DELETE"
                        onsubmit="return confirm('Xóa quyền truy cập của <?= htmlspecialchars((string) $m['username'], ENT_QUOTES, 'UTF-8') ?> khỏi thư viện?');">
                    <?= $csrf ?>
                    <button type="submit" class="btn btn-outline btn-sm" aria-label="Xóa thành viên này">
                      <?= $ic(['close'], 14) ?>
                    </button>
                  </form>
                </div>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Huong dan vai tro -->
<div class="card">
  <div class="card-head"><h2>Ý nghĩa các vai trò</h2></div>
  <div class="card-body">
    <div class="lib-grid">
      <?php foreach ($roleMap as $key => [$label, $cls, $desc]): ?>
        <div style="display:flex;flex-direction:column;gap:7px;padding:var(--s-3) var(--s-4);border:1px solid var(--border);border-radius:var(--r-sm);background:var(--surface-alt)">
          <span class="badge <?= $cls ?>" style="align-self:flex-start"><?= $label ?></span>
          <span class="dim" style="font-size:var(--t-xs);line-height:1.55"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>