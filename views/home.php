<?php
/** @var string $title */

$ic = static function (array $names, int $size = 16): string {
    $paths = [
        'logo'    => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 18.5z"/><path d="M4 10h16"/><path d="M9.5 14h5"/>',
        'library' => '<path d="M4 4v16"/><path d="M8 8v12"/><path d="M12 6v14"/><path d="M16 8v12"/><path d="M20 5v15"/>',
        'shield'  => '<path d="M12 22s8-4 8-10V5.5L12 2.5 4 5.5V12c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
        'wifiOff' => '<path d="M1 1l22 22"/><path d="M16.7 11.1A10.9 10.9 0 0 1 19 12.5"/><path d="M5 12.5a10.9 10.9 0 0 1 5.2-2.4"/><path d="M10.7 5a16 16 0 0 1 11.9 4"/><path d="M1.4 9a15.9 15.9 0 0 1 4.7-2.9"/><path d="M8.5 16.1a6 6 0 0 1 7 0"/><path d="M12 20h.01"/>',
        'arrowRight' => '<path d="M5 12h14"/><path d="M12.5 5.5L19 12l-6.5 6.5"/>',
    ];

    $body = '';
    foreach ($names as $n) {
        $body .= $paths[$n] ?? '';
    }

    return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" '
        . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
};
?>
<div class="container">

  <section class="hero">
    <div class="hero-mark"><?= $ic(['logo'], 28) ?></div>
    <h1>Lưu trang Web.<br>Đọc lại bất cứ lúc nào.</h1>
    <p>
      Dán đường dẫn một bài viết, WebArchive tải trang về máy chủ, làm sạch
      HTML và lưu ảnh cục bộ. Bạn đọc lại được ngay cả khi trang gốc đã
      ngừng hoạt động.
    </p>
    <div class="hero-actions">
      <a href="/login" class="btn btn-primary btn-lg">Đăng nhập</a>
      <a href="/register" class="btn btn-outline btn-lg">Tạo tài khoản miễn phí</a>
    </div>
  </section>

  <section style="max-width:1000px;margin:0 auto">
    <p class="section-label">Ba năng lực chính</p>

    <div class="lib-grid">
      <article class="feature">
        <div class="feature-icon" style="background:var(--brand-50);color:var(--brand-600)">
          <?= $ic(['library'], 20) ?>
        </div>
        <h3>Quản lý thư viện</h3>
        <p>
          Chia bài viết theo từng thư viện riêng, gắn nhãn phân loại và mời
          cộng tác viên với ba vai trò: chủ sở hữu, biên tập viên và người xem.
        </p>
      </article>

      <article class="feature">
        <div class="feature-icon" style="background:var(--green-100);color:var(--green-700)">
          <?= $ic(['shield'], 20) ?>
        </div>
        <h3>An toàn và riêng tư</h3>
        <p>
          Loại bỏ mọi script và sự kiện JavaScript khỏi nội dung lưu, chặn
          tài nguyên từ bên ngoài và kiểm tra địa chỉ trước khi thu thập.
        </p>
      </article>

      <article class="feature">
        <div class="feature-icon" style="background:var(--teal-100);color:var(--teal-600)">
          <?= $ic(['wifiOff'], 20) ?>
        </div>
        <h3>Đọc hoàn toàn offline</h3>
        <p>
          Ảnh được tải về máy chủ và bản đọc chạy độc lập — không còn phụ
          thuộc vào trang gốc, kể cả khi không còn kết nối mạng.
        </p>
      </article>
    </div>
  </section>

</div>