<?php

declare(strict_types=1);

/**
 * Tao 15 trang HTML thu nghiem cho DeTai16.
 * Chay: php bin/make_test_pages.php
 */

$base = dirname(__DIR__);
$dir = $base . '/test_server/pages';
if (!is_dir($dir)) {
    mkdir($dir, 0o775, true);
}

$head = static function (string $title, string $extraHead = '', string $lang = 'vi'): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
{$extraHead}
</head>
<body>
HTML;
};

$foot = <<<HTML
<footer>
  <p>&copy; 2024 Website thu nghiem DeTai16 - noi dung gia lap phuc vu kiem thu.</p>
</footer>
</body>
</html>
HTML;

$pages = [];

// ---- 03 / 04: Hai bai CUNG TIEU DE nhung NOI DUNG KHAC nhau.
// Hai trang nay duoc viet rieng voi dau tieng Viet day du
// (xem file trong test_server/pages/) de kiem chung viec khong gop nham.

// ---- 05. Trang KHONG co noi dung hop le (chi co menu, khong co bai viet).
$pages['05-trang-khong-co-noi-dung.html'] = $head('Trang trắng - Không có nội dung')
    . <<<HTML
<header>
  <nav><ul><li><a href="/">Trang chu</a></li><li><a href="/a">A</a></li></ul></nav>
</header>
<div class="shell">
  <aside class="sidebar">
    <ul>
      <li><a href="/x1">Muc 1</a></li>
      <li><a href="/x2">Muc 2</a></li>
      <li><a href="/x3">Muc 3</a></li>
    </ul>
  </aside>
  <div class="content">
    <p>Chao.</p>
  </div>
</div>
HTML
    . "\n" . $foot;

// ---- 06. Trang 404 (HTTP error).
$pages['06-loi-404.html'] = $head('404 - Không tìm thấy trang')
    . <<<HTML
<div class="error-page">
  <h1>404</h1>
  <h2>Khong tim thay trang ban tim kiem</h2>
  <p>Trang ban dang tim khong ton tai tren may chu nay. Vui long kiem tra lai duong dan
  hoac quay ve trang chu cua website de tiep tuc su dung dich vu cua chung toi.</p>
  <p>Neu ban tin rang trang nay nen ton tai, hay lien he voi quan tri vien de chung toi
  kiem tra va khac phuc ngay lap tuc.</p>
  <a href="/">Quay ve trang chu</a>
</div>
HTML
    . "\n" . $foot;

// ---- 07. Trang chua script + thuoc tinh su kien (kiem thu CN3).
$pages['07-trang-chua-script.html'] = $head('Bài viết chứa script và thuộc tính sự kiện')
    . <<<HTML
<script>
  // Script duoc chay truc tiep - phai bi loai bo khi luu
  document.write("<h1>NE DUOC CHEN VAO DOM</h1>");
  alert("Ban da bi hack!");
</script>
<script src="https://evil.example.com/malware.js"></script>
<body onload="alert('XSS onload')" onunload="alert('XSS onunload')">
<main>
<article>
  <h1>Bài viết chứa script và thuộc tính sự kiện</h1>
  <p>Trang nay co chu dich dua vao script va thuoc tinh su kien de kiem thung co an toan
  cua che do lam sach HTML co hoat dong dung hay khong. Khi bai viet duoc luu va mo lai,
  <strong>khong co script nao duoc chay</strong> va <strong>khong co thuoc tinh on* nao duoc giu lai</strong>.</p>
  <p onclick="alert('XSS onclick')">Doan van ban nay co thuoc tinh onclick duoc gan truc tiep vao the p.</p>
  <p>Doan van ban nay khong co thuoc tinh nguy hiem nao, nen no phai duoc giu nguyen.</p>
  <p><a href="javascript:alert('XSS javascript URL')">Lien ket javascript khong duoc phep</a></p>
  <p><a href="https://example.com/ok">Lien ket HTTP binh thuong duoc phep</a></p>
  <img src="/images/safe.png" alt="Anh binh thuong" onerror="alert('XSS onerror')" width="200" height="100">
  <p>Neu ban thay dong chu "NE DUOC CHEN VAO DOM" o tren trang doc lai, nghia la che do
  lam sach HTML da that bai va co lo hong bao mat.</p>
</article>
</main>
HTML
    . "\n" . $foot;

// ---- 08. Trang chuyen huong (test theo doi).
$pages['08-chuyen-huong-hop-le.html'] = $head('Đang chuyển hướng...')
    . <<<HTML
<meta http-equiv="refresh" content="0; url=/pages/01-article-hop-le.html">
<p>Dang chuyen huong...</p>
HTML
    . "\n" . $foot;

// ---- 09. Trang co iframe, object, embed (test loai bo).
$pages['09-trang-nhung-ngoai.html'] = $head('Bài viết có nhúng iframe')
    . <<<HTML
<main>
<article>
  <h1>Bài viết có nhúng iframe, object và embed</h1>
  <p>Trang nay chua cac thanh phan nhung khong duoc ho tro: iframe, object, embed.
  Khi luu bai viet nay, tat ca cac thanh phan do phai bi loai bo khoi ban doc lai
  de tranh tai ve ma duoc thuc thi tu nguon ben ngoai.</p>
  <iframe src="https://example.com/embed-video" width="560" height="315" frameborder="0"></iframe>
  <object data="/files/document.pdf" type="application/pdf" width="400" height="300"></object>
  <embed src="/files/movie.swf" type="application/x-shockwave-flash" width="400" height="300">
  <p>Doan van ban nay phai duoc giu nguyen sau khi cac thanh phan nhung bi loai bo.</p>
</article>
</main>
HTML
    . "\n" . $foot;

// ---- 10. Trang tieng Viet / Unicode day du.
$pages['10-tieng-viet-unicode.html'] = $head('Bài viết tiếng Việt đầy đủ dấu và ký tự đặc biệt')
    . <<<HTML
<main>
<article>
  <h1>Bài viết tiếng Việt đầy đủ dấu và ký tự đặc biệt</h1>
  <p class="byline">Tác giả: <span itemprop="author">Đặng Thị Hồng Nhung</span></p>
  <p>Tiếng Việt là một trong những ngôn ngữ phức tạp nhất thế giới với hệ thống thanh điệu
  gồm sáu thanh: ngang, huyền, sắc, hỏi, ngã, nặng. Mỗi thanh điệu thể hiện một ý nghĩa
  khác nhau, và việc nhận diện chính xác là điều kiện tiên quyết để xử lý ngôn ngữ tự nhiên.</p>
  <p>Trong lập trình, việc hiển thị đúng các ký tự tiếng Việt đòi hỏi cơ sở dữ liệu sử dụng
  bảng mã UTF-8 với cấu hình utf8mb4, cùng với việc thiết lập header Content-Type phù hợp
  để trình duyệt hiểu đúng mã hóa của nội dung.</p>
  <h2>Các ký tự đặc biệt cần lưu ý</h2>
  <p>Ngoài chữ cái có dấu, tiếng Việt còn sử dụng nhiều ký tự đặc biệt trong văn bản:
  dấu ngoặc kép « », dấu nháy đơn ‘ ’, dấu gạch dài —, dấu chấm lửng …, và ký hiệu đặc biệt
  như §, ¶, †, ‡, •, °, ±, ×, ÷, ≤, ≥, ≠, ∞, €.</p>
  <p>Với những ký tự như vậy, mọi hệ thống đều phải xử lý đúng cách mã hóa từ đầu đến cuối.
  Nếu có bất kỳ đâu trong chuỗi xử lý bị chuyển đổi sai cách mã hóa, người dùng sẽ nhìn thấy
  các ký tự lỗi như Â¥Ã¶ hoặc Ä‚ÄºÅ¾ thay vì nội dung tiếng Việt có dấu.</p>
  <p>Ngoài ra, tiếng Việt còn có thể viết tắt và văn hóa riêng: ví dụ "ko" thay vì "không",
  "đc" thay vì "được", "vs" thay vì "với". Việc xử lý ngôn ngữ tự nhiên trong các hệ thống
  tìm kiếm phải tính đến cả những trường hợp này.</p>
  <h2>Kết luận</h2>
  <p>Việc xử lý đúng tiếng Việt là yêu cầu cơ bản để xây dựng bất kỳ ứng dụng nào hướng đến
  người dùng Việt Nam. May mắn là PHP và MySQL hiện đại đã hỗ trợ đầy đủ cho yêu cầu này.</p>
</article>
</main>
HTML
    . "\n" . $foot;

// ---- 11. Trang co anh WebP (test dinh dang WebP).
$pages['11-bai-viet-anh-webp.html'] = $head('Bài viết chứa ảnh WebP')
    . <<<HTML
<main>
<article>
  <h1>Bài viết chứa ảnh định dạng WebP</h1>
  <p>WebP la dinh dang anh nang cao duoc Google phat trien, ho tro nen tot hon 30% so voi JPEG
  voi cung chat luong hinh anh. Trang nay chua mot anh dinh dang WebP de kiem chung viec luu
  anh cuc bo co ho tro dung dinh dang nay hay khong.</p>
  <p>So voi JPEG, thu algorithm WebP ho tro nen lossy va lossless, ho tro chuyen dong anh,
  va ho tro alpha channel. Nhieu trinh duyet hien nay da ho tro dinh dang nay mac dinh.</p>
  <figure>
    <img src="/images/anh-webp.webp" alt="Ảnh minh hoạ định dạng WebP" width="600" height="400">
    <figcaption>Ảnh minh hoạ định dạng WebP</figcaption>
  </figure>
  <p>Ngoai ra, anh JPEG va PNG cung duoc ho tro. Trang nay cung chua anh JPEG de kiem chung
  kha nang luu nhieu dinh dang anh khac nhau trong cung mot bai viet.</p>
  <img src="/images/anh-jpeg.jpg" alt="Ảnh minh hoạ định dạng JPEG" width="600" height="400">
</article>
</main>
HTML
    . "\n" . $foot;

// ---- 12. Trang thuoc tinh style, tai nguyen ngoai (test CSP + style).
$pages['12-tai-nguyen-ngoai.html'] = $head('Bài viết tham chiếu tài nguyên ngoài')
    . <<<HTML
<style>
  .custom { color: #e74c3c; font-weight: bold; }
  @import url("https://fonts.googleapis.com/css2?family=Roboto");
</style>
<link rel="stylesheet" href="https://cdn.example.com/style.css">
<main>
<article>
  <h1>Bài viết tham chiếu tài nguyên ngoài</h1>
  <p>Trang nay chua cac tai nguyen ben ngoai nhu stylesheet tu CDN va font tu Google Fonts.
  Ban doc lai phai khong tai ve bat ky tai nguyen ben ngoai nao, de dam bao hoat dong
  doc lap hoan toan va khong gui tin hieu ra ngoai khi mo bai viet.</p>
  <p class="custom">Doan van ban nay co lop CSS rieng trong trang nguon.</p>
  <img src="https://cdn.example.com/remote-image.jpg" alt="Ảnh từ CDN bên ngoài" width="400" height="300">
  <p>Neu anh tren khong hien thi trong ban doc lai, nghia la che do luu anh cuc bo da chan
  dung tai nguyen ben ngoai nhu cong bo.</p>
</article>
</main>
HTML
    . "\n" . $foot;

// ---- 13. Bai viet ngan (duoi nguong toi thieu) - phai bi tu choi.
$pages['13-bai-viet-qua-ngan.html'] = $head('Bài viết quá ngắn')
    . <<<HTML
<article>
  <h1>Bài viết quá ngắn</h1>
  <p>Noi dung nay quá ngan.</p>
</article>
HTML
    . "\n" . $foot;

// ---- 14. Bai viet chua nhieu the HTML phuc tap (bang, danh sach, code).
$pages['14-bai-viet-html-phuc-tap.html'] = $head('Bài viết có HTML phức tạp')
    . <<<HTML
<main>
<article>
  <h1>Bài viết chứa nhiều thẻ HTML phức tạp</h1>
  <p>Trang nay chua duoc nhieu loai the HTML khac nhau de kiem chung viec lam sach HTML
  va giu lai cac the an toan: bang, danh sach co thu tu, danh sach khong thu tu, trich dan,
  code, va cau truc tieu de.</p>

  <h2>Bảng dữ liệu</h2>
  <table>
    <caption><strong>Bảng 1:</strong> So sanh cach mo trang</strong></caption>
    <thead>
      <tr><th scope="col">Phuong phap</th><th scope="col">Uu diem</th><th scope="col">Nhuoc diem</th></tr>
    </thead>
    <tbody>
      <tr><td>Giai ma DOM</td><td>Chinh xac, nhanh</td><td>Can DOMDocument</td></tr>
      <tr><td>Bien dich chuoi</td><td>Don gian</td><td>Khong chan tai nguyen</td></tr>
      <tr><td>Thu vien ben ngoai</td><td>Day du tinh nang</td><td>Them phu thuoc</td></tr>
    </tbody>
  </table>

  <h2>Danh sách</h2>
  <ul>
    <li>Muc thu nhat</li>
    <li>Muc thu hai</li>
    <li>Muc thu ba voi <strong>van ban dam</strong></li>
  </ul>
  <ol>
    <li>Buoc 1: Kiem tra URL</li>
    <li>Buoc 2: Tai HTML</li>
    <li>Buoc 3: Trich xuat</li>
  </ol>

  <h2>Trích dẫn</h2>
  <blockquote cite="https://example.com/nguon">
    <p>Trich dan mau cho thay the blockquote duoc giu lai trong ban doc lai.</p>
  </blockquote>

  <h2>Đoạn mã</h2>
  <pre><code class="language-php">&lt;?php
function xinChao(string \$ten): string {
    return "Xin chao, {\$ten}!";
}</code></pre>
  <p>Neu <code>Xu ly thoi gian</code> va <code>Ket qua</code> xuat hien dung o ban doc lai,
  nghia la cac the monospace duoc giu lai.</p>

  <h3>Ke tieu cap 3</h3>
  <p>Va <em>chu nghieng</strong>, <strong>chu dam</strong>, va <mark>chu highlight</mark>.</p>
</article>
</main>
HTML
    . "\n" . $foot;

// ---- 15. Trang nhieu anh (test luu nhieu anh).
$pages['15-bai-viet-nhieu-anh.html'] = $head('Bài viết chứa nhiều ảnh')
    . <<<HTML
<main>
<article>
  <h1>Bài viết chứa nhiều ảnh</h1>
  <p>Trang nay chua nhieu anh o cac dinh dang khac nhau de kiem chung kha nang luu
  cuc bo va thay the tham chieu duong dan. Khi mo ban doc lai, tat ca cac anh nay
  phai duoc hien thi tu duong dan noi bo, khong tai ve lai tu trang nguon.</p>
  <figure>
    <img src="/images/anh-1.png" alt="Ảnh minh hoạ số 1" width="400" height="300">
    <figcaption>Anh minh hoa so 1</figcaption>
  </figure>
  <figure>
    <img src="/images/anh-2.jpg" alt="Ảnh minh hoạ số 2" width="400" height="300">
    <figcaption>Anh minh hoa so 2</figcaption>
  </figure>
  <figure>
    <img src="/images/anh-3.webp" alt="Ảnh minh hoạ số 3" width="400" height="300">
    <figcaption>Anh minh hoa so 3</figcaption>
  </figure>
  <p>Mot anh khong ton tai tren may chu nguon: <img src="/images/khong-ton-tai.jpg" alt="Ảnh không tồn tại" width="200" height="150"></p>
  <p>Trong truong hop anh khong luu duoc, ban doc lai phai hien thi thong bao thay the
  thay vi tu dong tai lai anh do tu trang nguon.</p>
</article>
</main>
HTML
    . "\n" . $foot;

foreach ($pages as $file => $content) {
    $path = $dir . '/' . $file;
    file_put_contents($path, $content);
    echo "[OK] {$file}\n";
}

echo "\nHoan tat. " . count($pages) . " trang da duoc tao trong {$dir}\n";
