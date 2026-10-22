<?php
/** @var string $title */
/** @var array $library */
/** @var array $pages */
/** @var string $csrf */
/** @var bool $canWrite */
/** @var array<string,string> $errors */

$ic = static function (array $names, int $s = 16): string {
    $paths = [
        'arrowLeft'   => '<path d="M19 12H5"/><path d="M11.5 18.5L5 12l6.5-6.5"/>',
        'library'     => '<path d="M4 4v16"/><path d="M8 8v12"/><path d="M12 6v14"/><path d="M16 8v12"/><path d="M20 5v15"/>',
        'file'        => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h4"/>',
        'globe'       => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/>',
        'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'type'        => '<path d="M4 7V4.5h16V7"/><path d="M9.5 20h5"/><path d="M12 4.5V20"/>',
        'plus'        => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'arrowRight'  => '<path d="M5 12h14"/><path d="M12.5 5.5L19 12l-6.5 6.5"/>',
        'imageOff'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="M21 15.5l-4.5-4.5"/><path d="M2 2l20 20"/>',
    ];
    $body = '';
    foreach ($names as $n) { $body .= $paths[$n] ?? ''; }
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
  <a href="/libraries/<?= (int) $library['id'] ?>"><?= htmlspecialchars((string) $library['name'], ENT_QUOTES, 'UTF-8') ?></a>
  <span class="sep" aria-hidden="true">›</span>
  <span aria-current="page">Bài đã lưu</span>
</nav>

<div class="page-head">
  <div>
    <span class="eyebrow">Thư mục</span>
    <h1>Bài đã lưu</h1>
    <p><b class="tnum"><?= count($pages) ?></b> bài viết trong thư viện <b><?= htmlspecialchars((string) $library['name'], ENT_QUOTES, 'UTF-8') ?></b>.</p>
  </div>
  <div class="page-head-actions">
    <a href="/libraries/<?= (int) $library['id'] ?>" class="btn btn-outline btn-sm">
      <?= $ic(['arrowLeft'], 14) ?> Về thư viện
    </a>
    <?php if ($canWrite): ?>
      <a href="/libraries/<?= (int) $library['id'] ?>#luu-trang" class="btn btn-primary btn-sm">
        <?= $ic(['plus'], 14) ?> Lưu trang Web
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($pages)): ?>
  <div class="card">
    <div class="empty">
      <div class="empty-icon"><?= $ic(['file'], 27) ?></div>
      <h3>Chưa có bài viết nào</h3>
      <?php if ($canWrite): ?>
        <p>Quay lại trang thư viện và nhập một địa chỉ URL để lưu bài viết đầu tiên.</p>
        <a href="/libraries/<?= (int) $library['id'] ?>#luu-trang" class="btn btn-primary">
          <?= $ic(['plus']) ?> Lưu trang Web
        </a>
      <?php else: ?>
        <p>Bạn có quyền xem thư viện này nhưng chưa có quyền thêm bài viết.</p>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <ul class="article-list">
      <?php foreach ($pages as $page): ?>
        <?php $snapId    = (int) ($page['snapshot_id'] ?? 0);
              $status    = (string) ($page['status'] ?? 'saved');
              [$statusLabel, $statusClass] = $statusMap[$status] ?? ['Chưa rõ', 'badge-muted'];
              $imageHash = (string) ($page['image_hash'] ?? '');
              $tags      = $page['tags'] ?? [];
              $href      = $snapId > 0 ? "/read/{$snapId}" : "/pages/{$page['id']}"; ?>
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
  </div>
<?php endif; ?>