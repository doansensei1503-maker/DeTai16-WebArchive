<?php
/** @var string $title */
/** @var array $libraries */
/** @var string $csrf */
/** @var array<string,string> $errors */

$ic = static function (array $names, int $s = 16): string {
    $paths = [
        'library'    => '<path d="M4 4v16"/><path d="M8 8v12"/><path d="M12 6v14"/><path d="M16 8v12"/><path d="M20 5v15"/>',
        'file'       => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h4"/>',
        'tag'        => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><path d="M7 7h.01"/>',
        'users'      => '<path d="M16 21v-1.8a3.6 3.6 0 0 0-3.6-3.6H6.6A3.6 3.6 0 0 0 3 19.2V21"/><circle cx="9.5" cy="7.5" r="3.6"/>',
        'plus'       => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'close'      => '<path d="M18 6L6 18"/><path d="M6 6l12 12"/>',
        'alert'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.5h.01"/>',
        'arrowRight' => '<path d="M5 12h14"/><path d="M12.5 5.5L19 12l-6.5 6.5"/>',
    ];

    $body = '';
    foreach ($names as $n) {
        $body .= $paths[$n] ?? '';
    }

    return '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $body . '</svg>';
};

$roleLabel = [
    'owner'  => ['Chủ sở hữu',   'badge-info'],
    'editor' => ['Biên tập viên', 'badge-teal'],
    'viewer' => ['Người xem',     'badge-muted'],
];
?>
<div class="page-head">
  <div>
    <span class="eyebrow">Quản lý</span>
    <h1>Thư viện của tôi</h1>
    <?php $n = count($libraries); ?>
    <p>
      <?php if ($n === 0): ?>
        Tạo thư viện đầu tiên để bắt đầu lưu giữ trang Web bạn muốn đọc lại.
      <?php else: ?>
        Bạn đang quản lý <b class="tnum"><?= $n ?></b> thư viện. Mỗi thư viện có thể chia sẻ với các thành viên ở vai trò Chủ sở hữu, Biên tập viên hoặc Người xem.
      <?php endif; ?>
    </p>
  </div>
  <div class="page-head-actions">
    <button type="button" class="btn btn-primary"
            aria-expanded="false" aria-controls="create-form"
            onclick="
              var f=document.getElementById('create-form');
              var open=f.classList.toggle('is-open');
              this.setAttribute('aria-expanded', open ? 'true' : 'false');
              if (open && document.getElementById('lib-name')) { document.getElementById('lib-name').focus(); }">
      <?= $ic(['plus']) ?> Tạo thư viện
    </button>
  </div>
</div>

<?php if (!empty($errors['general'])): ?>
  <div class="alert alert-error" role="alert">
    <?= $ic(['alert'], 18) ?><span><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></span>
  </div>
<?php endif; ?>

<!-- Form tao thu vien -->
<div id="create-form" class="card mb-5 hidden">
  <div class="card-head"><h2>Tạo thư viện mới</h2></div>
  <div class="card-body">
    <form method="POST" action="/libraries" novalidate>
      <?= $csrf ?>

      <div class="field">
        <label class="field-label" for="lib-name">Tên thư viện <span class="req">*</span></label>
        <input type="text" id="lib-name" name="name"
               class="input<?= !empty($errors['name']) ? ' is-invalid' : '' ?>"
               required maxlength="150" autofocus
               placeholder="Ví dụ: Bài viết CNTT"
               value="<?= htmlspecialchars((string) ($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <?php if (!empty($errors['name'])): ?>
          <span class="field-error"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php else: ?>
          <span class="field-hint">Tên nên ngắn gọn (tối đa 150 ký tự).</span>
        <?php endif; ?>
      </div>

      <div class="field">
        <label class="field-label" for="lib-desc">Mô tả <span class="opt">(tùy chọn)</span></label>
        <textarea id="lib-desc" name="description"
                  class="input<?= !empty($errors['description']) ? ' is-invalid' : '' ?>"
                  rows="2" maxlength="2000"
                  placeholder="Mô tả ngắn về nội dung thư viện…"><?= htmlspecialchars((string) ($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>

      <div class="flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary">Tạo thư viện</button>
        <button type="button" class="btn btn-ghost"
                onclick="document.getElementById('create-form').classList.remove('is-open')">
          <?= $ic(['close']) ?> Hủy
        </button>
      </div>
    </form>
  </div>
</div>

<?php if (empty($libraries)): ?>
  <div class="card">
    <div class="empty">
      <div class="empty-icon"><?= $ic(['library'], 27) ?></div>
      <h3>Chưa có thư viện nào</h3>
      <p>
        Thư viện là nơi nhóm các bài viết bạn lưu lại. Tạo một thư viện
        để bắt đầu thu thập và đọc lại nội dung trang Web.
      </p>
      <button type="button" class="btn btn-primary btn-lg"
              onclick="document.getElementById('create-form').classList.add('is-open');
                       document.getElementById('lib-name').focus();">
        <?= $ic(['plus']) ?> Tạo thư viện đầu tiên
      </button>
    </div>
  </div>
<?php else: ?>
  <div class="lib-grid">
    <?php foreach ($libraries as $lib): ?>
      <?php [$rLabel, $rClass] = $roleLabel[$lib['role']] ?? ['Thành viên', 'badge-muted']; ?>
      <article class="lib-card">
        <div class="lib-card-top">
          <div class="lib-icon"><?= $ic(['library'], 19) ?></div>
          <div style="min-width:0;flex:1">
            <h3 style="margin-bottom:3px">
              <a href="/libraries/<?= (int) $lib['id'] ?>"><?= htmlspecialchars((string) $lib['name'], ENT_QUOTES, 'UTF-8') ?></a>
            </h3>
          </div>
        </div>

        <?php if (!empty($lib['description'])): ?>
          <p class="lib-desc"><?= htmlspecialchars((string) $lib['description'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
          <p class="lib-desc is-empty">Chưa có mô tả.</p>
        <?php endif; ?>

        <div class="lib-foot">
          <span title="Số bài viết"><?= $ic(['file'], 13) ?> <b class="tnum"><?= number_format((int) ($lib['page_count'] ?? 0)) ?></b> bài</span>
          <span title="Số nhãn"><?= $ic(['tag'], 13) ?> <b class="tnum"><?= number_format((int) ($lib['tag_count'] ?? 0)) ?></b> nhãn</span>
          <span class="badge <?= $rClass ?>"><?= $rLabel ?></span>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>