<?php
/** @var string $title */
/** @var array $library */
/** @var array $pages */
/** @var array $stats */
/** @var string $csrf */
/** @var bool $canWrite */
/** @var bool $canManage */
/** @var array<string,string> $errors */

$ic = static function (array $names, int $s = 16): string {
    $paths = [
        'users'      => '<path d="M16 21v-1.8a3.6 3.6 0 0 0-3.6-3.6H6.6A3.6 3.6 0 0 0 3 19.2V21"/><circle cx="9.5" cy="7.5" r="3.6"/>',
        'plus'       => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'file'       => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h4"/>',
        'image'      => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="M21 15.5l-4.5-4.5L6 21"/>',
        'tag'        => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><path d="M7 7h.01"/>',
        'link'       => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'clock'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'type'       => '<path d="M4 7V4.5h16V7"/><path d="M9.5 20h5"/><path d="M12 4.5V20"/>',
        'alert'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.5h.01"/>',
        'save'       => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'shield'     => '<path d="M12 22s8-4 8-10V5.5L12 2.5 4 5.5V12c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
        'globe'      => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/>',
        'arrowRight' => '<path d="M5 12h14"/><path d="M12.5 5.5L19 12l-6.5 6.5"/>',
        'library'    => '<path d="M4 4v16"/><path d="M8 8v12"/><path d="M12 6v14"/><path d="M16 8v12"/><path d="M20 5v15"/>',
        'check'      => '<path d="M20 6L9 17l-5-5"/>',
        'imageOff'   => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="M21 15.5l-4.5-4.5"/><path d="M2 2l20 20"/>',
    ];

    $body = '';
    foreach ($names as $n) {
        $body .= $paths[$n] ?? '';
    }

    return '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $body . '</svg>';
};

$statusMap = [
    'saved'    => ['Đọc lại được', 'badge-ok'],
    'pending'  => ['Đang xử lý',   'badge-warn'],
    'failed'   => ['Thu thập lỗi', 'badge-error'],
];
?>
<nav class="crumbs" aria-label="Đường dẫn">
  <a href="/libraries"><?= $ic(['library'], 13) ?> Thư viện</a>
  <span class="sep" aria-hidden="true">›</span>
  <span aria-current="page"><?= htmlspecialchars((string) $library['name'], ENT_QUOTES, 'UTF-8') ?></span>
</nav>

<div class="page-head">
  <div>
    <span class="eyebrow">Thư viện</span>
    <h1><?= htmlspecialchars((string) $library['name'], ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if (!empty($library['description'])): ?>
      <p><?= htmlspecialchars((string) $library['description'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
      <p class="dim">Chưa có mô tả.</p>
    <?php endif; ?>
  </div>
  <div class="page-head-actions">
    <?php if ($canManage): ?>
      <a href="/libraries/<?= (int) $library['id'] ?>/members" class="btn btn-outline btn-sm">
        <?= $ic(['users'], 14) ?> Thành viên
      </a>
    <?php endif; ?>
    <?php if ($canWrite): ?>
      <a href="#luu-trang" class="btn btn-primary btn-sm"><?= $ic(['plus'], 14) ?> Lưu trang Web</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($errors['general'])): ?>
  <div class="alert alert-error" role="alert">
    <?= $ic(['alert'], 18) ?><span><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></span>
  </div>
<?php endif; ?>

<!-- Thong ke nhanh -->
<div class="card">
  <div class="card-body">
    <div class="stats">
      <div class="stat">
        <span class="stat-icon"><?= $ic(['file'], 15) ?></span>
        <span><b class="tnum"><?= number_format((int) ($stats['saved_pages'] ?? 0)) ?></b> bài đã lưu</span>
      </div>
      <div class="stat">
        <span class="stat-icon"><?= $ic(['image'], 15) ?></span>
        <span><b class="tnum"><?= number_format((int) ($stats['saved_images'] ?? 0)) ?></b> ảnh</span>
      </div>
      <div class="stat">
        <span class="stat-icon"><?= $ic(['tag'], 15) ?></span>
        <span><b class="tnum"><?= number_format((int) ($stats['tags'] ?? 0)) ?></b> nhãn</span>
      </div>
      <div class="stat">
        <span class="stat-icon"><?= $ic(['users'], 15) ?></span>
        <span><b class="tnum"><?= number_format((int) ($stats['members'] ?? 0)) ?></b> thành viên</span>
      </div>
      <?php if (($stats['failed_images'] ?? 0) > 0): ?>
        <div class="stat is-warn">
          <span class="stat-icon"><?= $ic(['alert'], 15) ?></span>
          <span><b class="tnum"><?= (int) $stats['failed_images'] ?></b> ảnh lỗi</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Form luu trang Web -->
<?php if ($canWrite): ?>
  <div class="card" id="luu-trang">
    <div class="card-head">
      <h2>Lưu trang Web mới</h2>
      <span class="hint"><?= $ic(['shield'], 14) ?> Hoạt động nội bộ, không cần trình gốc sau khi lưu</span>
    </div>
    <div class="card-body">
      <?php if (!empty($errors['url'])): ?>
        <div class="alert alert-error" role="alert">
          <?= $ic(['alert'], 18) ?><span><?= htmlspecialchars($errors['url'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="/libraries/<?= (int) $library['id'] ?>/pages"
            id="save-page-form" novalidate>
        <?= $csrf ?>

        <div class="field">
          <label class="field-label" for="url-input">Địa chỉ URL <span class="req">*</span></label>
          <div class="input-group">
            <input type="url" id="url-input" name="url"
                   class="input<?= !empty($errors['url']) ? ' is-invalid' : '' ?>"
                   required placeholder="https://example.com/bai-viet"
                   value="<?= htmlspecialchars((string) ($_POST['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                   <?= !empty($errors['url']) ? 'aria-invalid="true"' : '' ?>>
            <button type="submit" class="btn btn-primary" id="save-btn">
              <?= $ic(['save'], 16) ?> Lưu trang
            </button>
          </div>
          <span class="field-hint">
            Dán liên kết của bài viết. Hệ thống sẽ tải trang, trích xuất nội dung chính và lưu ảnh về máy chủ.
          </span>
        </div>

        <details class="muted" style="margin-top:6px">
          <summary style="cursor:pointer;font-size:var(--t-xs);color:var(--text-second)">
            Các URL được phép lưu là gì?
          </summary>
          <div style="margin-top:8px;padding:11px 13px;border:1px solid var(--border);border-radius:var(--r-sm);background:var(--surface-alt);font-size:var(--t-xs);color:var(--text-second);line-height:1.65">
            Chỉ chấp nhận <b>http</b> và <b>https</b>. Hệ thống sẽ <b>từ chối</b> nội bộ
            (localhost, 127.0.0.1, 10.x, 192.168.x, …) để tránh rủi ro SSRF và chỉ cho phép
            các đích được khai báo trong danh sách trắng nội bộ.
          </div>
        </details>
      </form>
    </div>
  </div>
<?php endif; ?>

<!-- Danh sach bai da luu -->
<div class="card">
  <div class="card-head">
    <h2>Danh sách bài đã lưu</h2>
    <span class="hint">
      <b class="tnum"><?= count($pages) ?></b> bài
      <?php if ($canWrite): ?> &middot; mới nhất đặt lên đầu <?php endif; ?>
    </span>
  </div>

  <?php if (empty($pages)): ?>
    <div class="empty">
      <div class="empty-icon"><?= $ic(['file'], 27) ?></div>
      <h3>Chưa có bài viết nào</h3>
      <?php if ($canWrite): ?>
        <p>Nhập một địa chỉ URL ở biểu mẫu phía trên để lưu bài viết đầu tiên vào thư viện này.</p>
      <?php else: ?>
        <p>Bạn có quyền xem thư viện này nhưng không có quyền thêm bài viết mới.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <ul class="article-list">
      <?php foreach ($pages as $page): ?>
        <?php $snapId = (int) ($page['snapshot_id'] ?? 0);
              $status  = (string) ($page['status'] ?? 'saved');
              [$statusLabel, $statusClass] = $statusMap[$status] ?? ['Chưa rõ', 'badge-muted'];
              $imageHash = (string) ($page['image_hash'] ?? '');
              $tags = $page['tags'] ?? [];
              $href = $snapId > 0 ? "/read/{$snapId}" : "/pages/{$page['id']}"; ?>
        <li class="article-card">
          <div class="article-thumb">
            <?php if ($imageHash !== ''): ?>
              <img src="/asset/<?= htmlspecialchars($imageHash, ENT_QUOTES, 'UTF-8') ?>" alt="">
            <?php else: ?>
              <div class="article-thumb-fallback"><?= $ic(['imageOff'], 34) ?></div>
            <?php endif; ?>
            <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
          </div>

          <div class="article-body">
            <h3 class="article-title">
              <a href="<?= $href ?>"><?= htmlspecialchars((string) ($page['title'] ?: 'Không có tiêu đề'), ENT_QUOTES, 'UTF-8') ?></a>
            </h3>

            <div class="article-source">
              <?= $ic(['globe'], 12) ?>
              <a href="<?= htmlspecialchars((string) $page['source_url'], ENT_QUOTES, 'UTF-8') ?>"
                 target="_blank" rel="noopener noreferrer">
                <span><?= htmlspecialchars(parse_url((string) $page['source_url'], PHP_URL_HOST) ?: $page['source_url'], ENT_QUOTES, 'UTF-8') ?></span>
              </a>
            </div>

            <?php if (!empty($page['excerpt'])): ?>
              <p class="article-excerpt"><?= htmlspecialchars((string) $page['excerpt'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <?php if (!empty($tags)): ?>
              <div class="article-tags">
                <?php foreach (array_slice($tags, 0, 4) as $tag):
                      $tagColor = (string) ($tag['color'] ?? '#748399');
                      if ($tagColor === '') { $tagColor = '#748399'; } ?>
                  <span class="tag">
                    <span class="tag-dot" style="background:<?= htmlspecialchars($tagColor, ENT_QUOTES, 'UTF-8') ?>"></span>
                    <?= htmlspecialchars((string) $tag['name'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                <?php endforeach; ?>
                <?php if (count($tags) > 4): ?>
                  <span class="tag muted">+<?= count($tags) - 4 ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="article-meta">
              <span title="Ngày lưu"><?= $ic(['clock'], 12) ?> <?= date('d/m/Y H:i', strtotime((string) $page['saved_at'])) ?></span>
              <?php if ((int) $page['word_count'] > 0): ?>
                <span title="Số từ"><?= $ic(['type'], 12) ?> <b class="tnum"><?= number_format((int) $page['word_count']) ?></b> từ</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="article-actions">
            <?php if ($snapId > 0): ?>
              <a href="/read/<?= $snapId ?>" class="btn btn-primary btn-sm">
                <?= $ic(['arrowRight'], 14) ?> Đọc lại
              </a>
            <?php endif; ?>
            <a href="/pages/<?= (int) $page['id'] ?>" class="btn btn-outline btn-sm">Chi tiết</a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php if ($canWrite): ?>
<script>
// Hien trang thai "dang thu thap" khi nguoi dung bam Luu trang de
// viec fetch chay tren may chu co the mat vai giay.
document.getElementById('save-page-form').addEventListener('submit', function () {
  var btn = document.getElementById('save-btn');
  btn.classList.add('is-loading');
  btn.setAttribute('disabled', 'disabled');

  var status = document.createElement('div');
  status.className = 'fetching';
  status.innerHTML =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
    + 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    + '<circle cx="12" cy="12" r="9"/>'
    + '<path d="M12 7v5l3.5 2"/></svg>'
    + '<span>Đang thu thập nội dung trang Web — quá trình này có thể mất vài giây…</span>'
    + '<div class="progress"><i></i></div>';
  this.appendChild(status);
});
</script>
<?php endif; ?>