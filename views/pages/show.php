<?php
/** @var string $title */
/** @var array $page */
/** @var array $tags */
/** @var array $snapshots */
/** @var string $csrf */
/** @var string $csrfToken  Token tho cho <script> */
/** @var bool $canWrite */
/** @var array<string,string> $errors */

$ic = static function (array $names, int $s = 16): string {
    $paths = [
        'arrowLeft'   => '<path d="M19 12H5"/><path d="M11.5 18.5L5 12l6.5-6.5"/>',
        'library'     => '<path d="M4 4v16"/><path d="M8 8v12"/><path d="M12 6v14"/><path d="M16 8v12"/><path d="M20 5v15"/>',
        'link'        => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'user'        => '<path d="M19.5 20.5v-1.8a3.6 3.6 0 0 0-3.6-3.6H8.1a3.6 3.6 0 0 0-3.6 3.6v1.8"/><circle cx="12" cy="7.5" r="3.6"/>',
        'type'        => '<path d="M4 7V4.5h16V7"/><path d="M9.5 20h5"/><path d="M12 4.5V20"/>',
        'tag'         => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><path d="M7 7h.01"/>',
        'book'        => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'trash'       => '<path d="M3.5 6h17"/><path d="M8.5 6V4.2a1.2 1.2 0 0 1 1.2-1.2h4.6a1.2 1.2 0 0 1 1.2 1.2V6"/><path d="M18.5 6v13a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2V6"/><path d="M10 11v5.5"/><path d="M14 11v5.5"/>',
        'close'       => '<path d="M18 6L6 18"/><path d="M6 6l12 12"/>',
        'globe'       => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/>',
        'arrowRight'  => '<path d="M5 12h14"/><path d="M12.5 5.5L19 12l-6.5 6.5"/>',
    ];
    $body = '';
    foreach ($names as $n) { $body .= $paths[$n] ?? ''; }
    return '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $body . '</svg>';
};

$pageId = (int) $page['id'];
?>
<nav class="crumbs" aria-label="Đường dẫn">
  <a href="/libraries"><?= $ic(['library'], 13) ?> Thư viện</a>
  <span class="sep" aria-hidden="true">›</span>
  <a href="/libraries/<?= (int) $page['library_id'] ?>"><?= htmlspecialchars((string) $page['library_name'], ENT_QUOTES, 'UTF-8') ?></a>
  <span class="sep" aria-hidden="true">›</span>
  <span aria-current="page">Chi tiết bài viết</span>
</nav>

<div class="page-head">
  <div>
    <span class="eyebrow">Bài viết</span>
    <h1><?= htmlspecialchars((string) ($page['title'] ?: 'Không có tiêu đề'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p>
      Đã lưu vào <b><?= date('d/m/Y H:i', strtotime((string) $page['saved_at'])) ?></b>
      bởi <b><?= htmlspecialchars((string) $page['created_by_username'], ENT_QUOTES, 'UTF-8') ?></b>.
    </p>
  </div>
  <div class="page-head-actions">
    <a href="/libraries/<?= (int) $page['library_id'] ?>" class="btn btn-outline btn-sm">
      <?= $ic(['arrowLeft'], 14) ?> Về thư viện
    </a>
    <?php if (!empty($snapshots)): ?>
      <a href="/read/<?= (int) $snapshots[0]['id'] ?>" class="btn btn-primary btn-sm">
        <?= $ic(['arrowRight'], 14) ?> Đọc lại
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($errors['general'])): ?>
  <div class="alert alert-error" role="alert">
    <?= $ic(['close'], 18) ?><span><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></span>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2>Thông tin</h2>
  </div>
  <div class="card-body">
    <!-- Metadata -->
    <div class="stats" style="margin-bottom:var(--s-3)">
      <?php if (!empty($page['author'])): ?>
        <div class="stat"><span class="stat-icon"><?= $ic(['user'], 15) ?></span><span><?= htmlspecialchars((string) $page['author'], ENT_QUOTES, 'UTF-8') ?></span></div>
      <?php endif; ?>
      <?php if ((int) $page['word_count'] > 0): ?>
        <div class="stat"><span class="stat-icon"><?= $ic(['type'], 15) ?></span><span><b class="tnum"><?= number_format((int) $page['word_count']) ?></b> từ</span></div>
      <?php endif; ?>
      <div class="stat"><span class="stat-icon"><?= $ic(['clock'], 15) ?></span><span><?= date('d/m/Y H:i', strtotime((string) $page['saved_at'])) ?></span></div>
    </div>

    <div class="article-source" style="margin-top:var(--s-4)">
      <?= $ic(['globe'], 12) ?>
      <a href="<?= htmlspecialchars((string) $page['source_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
        <span><?= htmlspecialchars((string) $page['source_url'], ENT_QUOTES, 'UTF-8') ?></span>
      </a>
    </div>

    <?php if (!empty($page['excerpt'])): ?>
      <blockquote style="border-left:3px solid var(--brand-400);padding:8px 0 8px 15px;margin:var(--s-5) 0 0;color:var(--text-second);font-style:italic;font-size:var(--t-md);line-height:1.62">
        <?= htmlspecialchars((string) $page['excerpt'], ENT_QUOTES, 'UTF-8') ?>
      </blockquote>
    <?php endif; ?>
  </div>
</div>

<!-- Nhan -->
<div class="card">
  <div class="card-head">
    <h2><?= $ic(['tag'], 14) ?> Nhãn</h2>
    <?php if ($canWrite): ?>
      <span class="hint">Gắn nhãn để phân loại và tìm lại bài nhanh hơn.</span>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div id="tag-list" class="article-tags">
      <?php foreach ($tags as $tag):
            $tagColor = (string) ($tag['color'] ?? '#748399');
            if ($tagColor === '') { $tagColor = '#748399'; } ?>
        <span class="tag" style="border-color:<?= htmlspecialchars($tagColor, ENT_QUOTES, 'UTF-8') ?>;background:color-mix(in srgb, <?= htmlspecialchars($tagColor, ENT_QUOTES, 'UTF-8') ?> 12%, white)">
          <span class="tag-dot" style="background:<?= htmlspecialchars($tagColor, ENT_QUOTES, 'UTF-8') ?>"></span>
          <?= htmlspecialchars((string) $tag['name'], ENT_QUOTES, 'UTF-8') ?>
          <?php if ($canWrite): ?>
            <button type="button" class="tag-x"
                    aria-label="Gỡ nhãn <?= htmlspecialchars((string) $tag['name'], ENT_QUOTES, 'UTF-8') ?>"
                    onclick="removeTag(<?= $pageId ?>, <?= (int) $tag['id'] ?>, this)">
              <?= $ic(['close'], 12) ?>
            </button>
          <?php endif; ?>
        </span>
      <?php endforeach; ?>

      <?php if ($canWrite): ?>
        <label class="sr-only" for="add-tag-select">Gắn nhãn cho bài viết</label>
        <select id="add-tag-select" class="input"
                style="width:auto;font-size:var(--t-xs);padding:4px 30px 4px 10px"
                onchange="addTag(<?= $pageId ?>, this)">
          <option value="">+ Gắn nhãn mới</option>
        </select>
      <?php elseif (empty($tags)): ?>
        <span class="muted">Chưa có nhãn nào.</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Phien ban doc lai -->
<?php if (!empty($snapshots)): ?>
  <div class="card">
    <div class="card-head">
      <h2><?= $ic(['book'], 14) ?> Phiên bản đọc lại</h2>
      <span class="hint"><?= count($snapshots) ?> bản</span>
    </div>
    <div class="card-body">
      <p class="dim" style="margin-bottom:var(--s-4)">
        Mỗi lần lưu lại tạo một bản snapshot độc lập — đọc được ngay cả khi trang gốc không còn tồn tại.
      </p>
      <div class="flex gap-2 flex-wrap">
        <?php foreach ($snapshots as $snap): ?>
          <a href="/read/<?= (int) $snap['id'] ?>" class="btn btn-outline btn-sm" style="gap:8px">
            <span style="font-weight:650;color:var(--brand-700)">v<?= (int) $snap['version_no'] ?></span>
            <span class="dim">· <b class="tnum"><?= number_format((int) $snap['text_length']) ?></b> ký tự</span>
            <?php if ((int) $snap['image_count'] > 0): ?>
              <span class="dim">· <?= (int) $snap['image_count'] ?> ảnh</span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($canWrite): ?>
  <div class="card">
    <div class="card-head">
      <h2>Khu vực nguy hiểm</h2>
    </div>
    <div class="card-body">
      <p class="dim" style="margin-bottom:var(--s-4)">
        Xóa vĩnh viễn bài viết này khỏi thư viện. Hành động không thể hoàn tác.
      </p>
      <form method="POST" action="/pages/<?= $pageId ?>?_method=DELETE"
            onsubmit="return confirm('Xóa vĩnh viễn bài viết này khỏi thư viện? Hành động này không thể hoàn tác.');">
        <?= $csrf ?>
        <button type="submit" class="btn btn-danger btn-sm">
          <?= $ic(['trash'], 14) ?> Xóa bài viết
        </button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($canWrite): ?>
<script>
// Token tho duoc Server render vao JS (khong phai HTML <input>).
const CSRF_TOKEN = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>;

// Nap danh sach nhan co the gan cho bai viet nay.
fetch('/libraries/<?= (int) $page['library_id'] ?>/tags')
  .then(r => r.ok ? r.json() : null)
  .then(data => {
    const sel = document.getElementById('add-tag-select');
    if (!sel || !data || !Array.isArray(data.tags)) return;
    for (const t of data.tags) {
      const opt = document.createElement('option');
      opt.value = t.id;
      opt.textContent = t.name;
      sel.appendChild(opt);
    }
  })
  .catch(() => {});

async function addTag(pageId, selectEl) {
  const tagId = selectEl.value;
  if (!tagId) return;

  const fd = new FormData();
  fd.append('_token', CSRF_TOKEN);
  fd.append('tag_id', tagId);

  try {
    const r = await fetch(`/pages/${pageId}/tags`, { method: 'POST', body: fd });
    if (r.ok) { location.reload(); return; }
    const err = await r.json().catch(() => ({}));
    alert(err.error || 'Không gắn được nhãn.');
  } catch (e) {
    alert('Không gắn được nhãn: ' + e.message);
  } finally {
    selectEl.value = '';
  }
}

async function removeTag(pageId, tagId, btn) {
  if (!confirm('Gỡ nhãn này khỏi bài viết?')) return;

  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('_token', CSRF_TOKEN);
    fd.append('_method', 'DELETE');

    const r = await fetch(`/pages/${pageId}/tags/${tagId}`, { method: 'POST', body: fd });
    if (r.ok) { location.reload(); return; }
    const err = await r.json().catch(() => ({}));
    alert(err.error || 'Không gỡ được nhãn.');
  } catch (e) {
    alert('Không gỡ được nhãn: ' + e.message);
  } finally {
    btn.disabled = false;
  }
}
</script>
<?php endif; ?>