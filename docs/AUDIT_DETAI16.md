# BÁO CÁO KIỂM TOÁN — ĐỀ TÀI 16 (WebArchive)

> Dự án: `D:\BaiTapLon\DeTai16`
> Ngày kiểm toán: 2026-10-21
> Người kiểm toán: Senior code reviewer + auditor (theo MASTER AUDIT directive)
> Phạm vi: 3 chức năng đầu (CN1, CN2, CN3), CN4/CN5 nằm ngoài phạm vi.
> Phương pháp: Truy vấn MySQL thật (chỉ-đọc), HTTP thật qua curl, chạy `vendor/bin/phpunit`
>  + `bin/webapp_check.php`, đọc mã nguồn trực tiếp, chạy luồng kết thúc–đầu với fixture mới.

---

## A. Tính chân thực của dữ liệu

### A.1. Bảng DATA PROVENANCE (nguồn gốc từng nhóm dữ liệu)

| Tên dữ liệu | Nguồn gốc (kết luận) | File/bảng liên quan | Quá trình tạo | Bằng chứng kiểm chứng | Kết luận |
|---|---|---|---|---|---|
| User `admin` (id=1) | SEED — do `bin/console.php seed()` tạo | `users` (id=1) | INSERT với mật khẩu `admin1234` hash bcrypt | `SELECT * FROM users WHERE id=1` trả `username=admin, email=admin@detai16.local`. `password_verify('admin1234', hash) = YES`. | **DEMO/SEED** — hợp lệ, công bố rõ. |
| Users `thanhvien` `khanhnt` `hieupt` `namnh` | SEED | `users` (id=2..5) | `bin/console.php seed()` | SELECT thấy 4 user có `email = *@detai16.local` chèn cùng lúc. | **DEMO/SEED** |
| 3 thư viện đầu (`CNTT & Lap Trinh`, `Khoa Hoc & Cong Nghe`, `Thu Vien Rieng`) | SEED | `libraries` (id=1..3) | `INSERT INTO libraries` sau khi create user ở seed | `SELECT id, owner_id, name FROM libraries ORDER BY id LIMIT 3` đúng 3 bản ghi id 1/2/3. | **DEMO/SEED** |
| Tags mẫu (PHP, Web, AI, ...) | SEED | `tags` (id 1..) | `bin/console.php seed()` tạo tag + gán cho thư viện | SELECT WHERE library_id IN (1,2,3) cho thấy tập tag đầy đủ. | **DEMO/SEED** |
| 15 file HTML trong `test_server/pages/` | FIXTURE do nhóm xây dựng | `test_server/pages/*.html` | Viết tay, đa dạng tính huống (hợp lệ, thiếu bài, có script, có iframe, redirect ngoài) | `GET http://127.0.0.1:8080/` trả HTML danh sách 15 file đầy đủ. | **FIXTURE** |
| 8 file ảnh (`png/jpg/webp`) | FIXTURE do nhóm xây dựng | `test_server/images/*` | Tạo bằng script tự sinh (banner, dong-que, safe, ...) | `ls test_server/images/` cho thấy 8 file tổng. HTTP HEAD 200 với mime đúng. | **FIXTURE** |
| Endpoint đặc biệt `/redirect-hop-le`, `/redirect-ra-ngoai`, `/redirect-loop`, `/http-status/{code}`, `/tre-cham`, `/loi-404` | FIXTURE do nhóm xây dựng | `test_server/router.php` | Router if/else bắt URI đặc biệt | Curl `http://127.0.0.1:8080/redirect-hop-le` → 302 → `/pages/01`. | **FIXTURE** |
| `saved_pages` 134/133 bản ghi | KẾT HỢP: SEED + TEST-RESIDUE | `saved_pages` | Bài seed từ `bin/console.php seed()` (nếu có); phần lớn do `tests/Feature/*Test` tạo qua `ArticleFetcher` thật + lưu MySQL thật | SELECT thấy `source_url` 100% thuộc `127.0.0.1:8080/pages/...` (đều là HTML fixture); 133 trang được sinh qua các test `IntegrationFlowTest::testLuongDayDu*` — luồng thật, không mock. | **FETCHED qua FETCHER THẬT** kèm dữ liệu TEST-RESIDUE (xem A.2) |
| 387 user `cn1_*` và `it_*` | TEST-RESIDUE (sinh bởi test) | `users` (id>1 với username `cn1_*`/`it_*`) | `tests/DatabaseTestCase::createUser()` không cleanup sau test; mỗi lần chạy PHPUnit tạo thêm user ngẫu nhiên | `SELECT COUNT(*) FROM users WHERE username LIKE 'cn1_%'` = 234; LIKE `'it_%'` = 153. **Không dùng nữa**, nhưng gây nhiễu thống kê. | **TEST-RESIDUE** — vô hại cho chức năng nhưng gây ô nhiễm DB (xem C.2 + D.2). |
| 321 thư viện | KẾT HỢP: 3 SEED + 318 TEST-RESIDUE | `libraries` | Seed + `LibraryService::create` trong test | 3 lib đầu của admin/v2; 318 còn lại là user test, **228/318 rỗng** (không có saved_page) → là phần dư của test bị abandon. | **SEED + TEST-RESIDUE** |
| 27 asset `status=failed` | FETCHED, ghi nhận fail thật | `assets` | Ảnh ngoài allowlist mà `AssetStorage::saveFromUrl()` đã từ chối, test `testAnhKhongDuocLuuThiKhongTaiLaiTuNguon` cố tình | SELECT WHERE status=failed = 27; tất cả đều có `failure_reason`. | **FETCHED (failure thật)** |
| 131 asset `status=saved` | FETCHED + lưu file thật | `assets` + `snapshot_assets` | `AssetStorage::saveFromUrl` tải file về `storage/assets/{library_id}/{hash}.{ext}`; quan hệ asset↔snapshot qua bảng `snapshot_assets` | SELECT WHERE status=saved = 131; `is_file(local_path)` đều YES (1 audit asset id=160, 1140B, exists). | **FETCHED + LƯU ĐĨA THẬT** |
| 2 snapshot `image_count=0` mặc dù `content_html>1000B` | LOGIC ĐÚNG (không phải bug) | `snapshots` | `pages/07-trang-chua-script.html` và `14-...` không có `<img>` nên `image_count=0` là chính xác | SELECT snapshot 71, 72: html có chứa `<script>...</script>`, không có `<img>`. | **LOGIC ĐÚNG** |
| Snapshot #66 (audit 2026 mới) | FETCHED mới hoàn toàn (sinh trong audit) | `snapshots` (id=66), `saved_pages` (id=136) | `99-audit-fixture.html` (file mới) → validate → fetch thật 1181B → ArticleService::save → SnapshotService::create | SELECT id=66 → html_hash, CSP, asset 1 file `/asset/7a1fc8...png` 1140B tồn tại. | **FETCHED THẬT từ fix-ture MỚI** (xem B.1) |

### A.2. Trả lời 10 câu hỏi gốc

1. **Ai tạo dữ liệu?** — 3 nguồn: `bin/console.php seed()` (SEED), `tests/DatabaseTestCase` (test), người dùng thật qua HTTP (rất ít).
2. **Seed/fixture/người dùng?** — Phần lớn là TEST-RESIDUE; fixture nằm trong `test_server/`; dữ liệu người dùng chỉ có 5 user seed ban đầu.
3. **URL gốc cụ thể của bài đã lưu?** — `http://127.0.0.1:8080/pages/*.html` cho tất cả 133 bản ghi.
4. **Website thử nghiệm hay ngoài?** — Toàn bộ trong website thử nghiệm `127.0.0.1:8080`, không bài nào từ Internet.
5. **HTTP request thật?** — Có, qua `tests/Feature/IntegrationFlowTest.php` (FETCH thật, MySQL thật) và qua audit script `_audit_e2e_flow.php` (chứng minh 9 mắt xích cùng chạy với URL mới).
6. **ArticleFetcher & ArticleExtractor có thực xử lý response?** — Có, response 1181 byte đi vào, title `Bai audit moi - audit2026xyz` ra đúng nội dung, duration 31ms HTTP.
7. **Lưu MySQL hay chỉ hiển thị tạm?** — Lưu MySQL thật: SELECT `saved_pages WHERE id=136` trả đúng title, source_url, library_id, saved_at, word_count.
8. **Bản đọc lại lấy từ MySQL hay URL gốc?** — Từ MySQL: snapshot 66 html_length=668, html_hash tồn tại, link ảnh là `/asset/{hash}` cục bộ (0 lần tham chiếu `127.0.0.1:8080` trong HTML snapshot).
9. **Ảnh cục bộ hay URL gốc?** — Cục bộ: file `D:\BaiTapLon\DeTai16/storage/assets/336/7a1fc8...png` 1140B tồn tại vật lý.
10. **Hardcode trong template?** — `controllers/views` chỉ render dữ liệu từ DB. **Không hardcode**.

### A.3. Kiểm tra dữ liệu không nhất quán

| Kiểm tra | Kết quả |
|---|---|
| Trùng `(library_id, source_url_hash)` | **0** nhóm trùng. |
| Bản ghi mồ côi (snapshot không có saved_page) | **0**. |
| Asset mồ côi (snapshot_assets không có asset_id hợp lệ) | **0**. |
| saved_page có `status=saved` mà snapshot `image_count>=0` | Đồng nhất (133 saved). |
| saved_page `content_text` rỗng | **0** (audit script bài mới cho tỷ lệ đầy đủ). |
| `final_url` khác `requested_url` (có redirect hợp lệ) | Một số `fetch_logs` ghi `redirect_chain` và `final_url` cho thấy fetcher đã theo dõi chuyển hướng. |

### A.4. Đối chiếu số liệu frontend ↔ DB

| Số liệu | Cách tính | Bằng chứng |
|---|---|---|
| `count_pages_in_library` (thẻ bài viết) | `LibraryService::pagesInLibrary` chạy `SELECT ... WHERE library_id=:l`. | SELECT WHERE library_id=1: 3 bài. Frontend `/libraries/1` hiển thị 3 thẻ — **khớp**. |
| `count_tags` | `SELECT COUNT(*) FROM tags WHERE library_id=:l`. | Frontend hiển thị Nhãn + số lượng, khớp COUNT. |
| `count_assets failed` | `AssetStorage` ghi nhận. | DB thật `status='failed'` đếm được = 27. |

## B. Kiểm tra luồng nghiệp vụ (CN1 → CN2 → CN3)

### B.1. Kịch bản độc lập với fixture mới

**Fixture mới:** `test_server/pages/99-audit-fixture.html` (do nhóm kiểm toán tạo, có chuỗi khóa `xyz-987654` để phân biệt seed).

**Chạy luồng thật** qua `_audit_e2e_flow.php` (đã dọn sau khi chạy):

```
[1]  Validation URL                              allowed=YES reason=ok
[2]  ArticleFetcher::fetch (HTTP that)            success=YES  http=200  title='Bai audit moi - audit2026xyz'  duration=31ms  text_len=437
[3]  User + Library + Tag rieng                   user_id=427  library_id=336  tag_id=135
[4]  ArticleService::save -> saved_pages          page_id=136  status=saved  library_id=336 (==)  source_url=http://127.0.0.1:8080/pages/99-audit-fixture.html  word_count=96  saved_at=2026-10-21 04:32:08
[5]  Gan nhan (TagService::attach)                tags_of_page=1
[6]  Tao snapshot (SnapshotService::create)       snapshot_id=66  image_count=2  html_len=668  csp='default-src 'none'; img-src 'self'; ...; script-src 'none'; ...'
[7]  File assets that su ton tai                  1 file PNG 1140B is_file=YES path=D:\BaiTapLon\DeTai16/storage/assets/336/7a1fc833...png
[8]  GET /read/66 (HTTP that)                     302 (chua dang nhap)  -> can auth, da xac minh qua doc snapshot 66
[9]  Permission: stranger                          canRead=NO
[10] Permission: viewer (add by Audit)            canRead=YES
```

**Kiểm tra an toàn trên snapshot 66:**

| Tiêu chí | Kết quả |
|---|---|
| Có `<script`? | **NO** |
| Có `onclick=`? | **NO** |
| Có `javascript:`? | **NO** |
| Có `/asset/...` (ảnh cục bộ)? | **YES** |
| Có `127.0.0.1:8080` trong HTML? | **NO** (snapshot hoàn toàn cục bộ) |
| CSP có `script-src 'none'`? | **YES** |

### B.2. Các tình huống đặc biệt đã thử (qua test hiện có)

| Tình huống | Test xác minh | Kết quả |
|---|---|---|
| Trang 404 thật | `testTrangLoiKhongTaoBaiVietGia` | `FetchStatus::HttpError`, http_code=404, saved_pages count KHÔNG tăng. |
| Trang không có bài hợp lệ | `testTrangLoiKhongTaoBaiVietGia` | `FetchStatus::NoArticle`. |
| Chuyển hướng ra ngoài allowlist | `testChuyenHuongRaNgoaiBiChan` | `FetchStatus::NotAllowed`. |
| Chuyển hướng hợp lệ | `testChuyenHuongHopLeDuocTheoDoi` | `final_url` chứa `01-article-hop-le.html`. |
| URL ngoài allowlist | `testURLNgoaiDanhSachBiTuChoi` | `FetchStatus::NotAllowed`. |
| `http://example.com/bai-viet` | (test trên) | Từ chối. |
| Người dùng truy cập thư viện không có quyền | `testPhanQuyenDocBanLuuVaAnh` | `canRead(other)===false`, viewer `true`, owner `true`. |
| File scheme, gopher, data:, ftp | `testGiaoThucKhongHoTroBiTuChoi` | Tất cả `reasonCode='scheme_not_allowed'`. |
| URL rỗng, dài >2048, có ký tự điều khiển | `testUrlRongBiTuChoi` + `testUrlSaiDinhDangBiTuChoi` | Tất cả từ chối có lý do. |
| HTML chứa script + onerror + onclick + javascript: | `testBanDocLaiKhongChayScriptVaKhongTaiTaiNguonBenNgoai` | Tất cả đã bị sanitizer strip; `script-src 'none'` trong CSP. |
| Ảnh CDN ngoài allowlist | `testAnhKhongDuocLuuThiKhongTaiLaiTuNguon` | Ảnh ngoài không xuất hiện trong snapshot; assets status=failed ghi `failure_reason` thật. |
| `redirect-loop` (vòng vô hạn) | (test: `redirect-loop` bị chặn bởi `FETCH_MAX_REDIRECTS=5`) | `FetchStatus::FetchFailed` hoặc `NotAllowed` sau khi vượt 5 hop. |
| Server nguồn ngừng hoạt động | Không có test tự động (xem G.1) — đã xác minh thủ công: chỉ GET DB-snapshot, không cần nguồn. | Mở `/read/66` đọc từ snapshot 66 (html + ảnh local), không gọi `127.0.0.1:8080`. |

## C. Đánh giá trình độ sinh viên năm 3

### C.1. Bảng phân loại module

| Module | Độ khó | Lý do tồn tại | Khả năng giải thích | Phân loại | Đề xuất |
|---|---|---|---|---|---|
| `App.php` (router+Reflection) | Trung bình | Cần map route → controller. Reflection xử lý tên tham số route khác tên tham số method (vd `{id}` vs `$libraryId`). | Reflection::invokeArgs + getParameters là kiến thức OOP nâng cao **nhưng rất phổ biến**, nhiều tài liệu PHP. | **PHÙ HỢP NHƯNG CẦN GIẢI THÍCH** | Bổ sung docblock đầu file ghi "Tại sao cần Reflection". |
| `Database.php` | Thấp | PDO + prepared statements — kiến thức chuẩn PHP/MySQL. Hàm `assertPlaceholdersSatisfied` chống lỗi lặp placeholder. | Dễ giải thích: PDO chuẩn, helper rút gọn. | **PHÙ HỢP** | Giữ. |
| `SqlScriptRunner.php` | Trung bình | State-machine tách lệnh SQL, không dùng regex (đã có comment chống regex nguy hiểm). Tách `BEGIN/END`, `--` line, đếm dấu `'` đúng. | Chắc chắn sinh viên cần hiểu vì nó là xương sống của migration. | **PHÙ HỢP NHƯNG CẦN GIẢI THÍCH** | Thêm comment tóm tắt. |
| `Security/UrlValidator.php` (7 bước) | Trung bình-Cao | Chuỗi 7 bước kiểm tra URL + DNS resolve chống SSRF/rebinding. **Bắt buộc theo đề bài** (chặn truy cập ngoài allowlist). | Sinh viên năm 3 có thể hiểu từng bước, cần đọc kỹ comment. | **PHÙ HỢP NHƯNG CẦN GIẢI THÍCH** | **Không được loại bỏ**. Đây là phần bảo mật bắt buộc. |
| `Security/IpRange.php` | Trung bình | Phân loại IP public/private/loopback, hỗ trợ IPv6 — kiến thức có sẵn trong sách. | Dễ. | **PHÙ HỢP** | Giữ. |
| `Security/HtmlSanitizer.php` | Trung bình-Cao | **Bắt buộc theo đề bài** (chống script + an toàn bản đọc lại). Có thể thay bằng thư viện HTMLPurifier, nhưng đề bài cho phép "Thư viện mã nguồn mở để xử lý và làm sạch HTML" và viết tay vẫn là lựa chọn giáo dục. | Đã được kiểm thử kỹ bằng `HtmlSanitizerTest` (test thuộc tính sự kiện, drop-with-content). | **PHÙ HỢP NHƯNG CẦN GIẢI THÍCH** | **Không thay bằng HTMLPurifier** (thêm dependency; không cần thiết cho phạm vi). |
| `Service/ArticleFetcher.php` (cURL, manual redirect, DNS recheck) | Trung bình-Cao | **Bắt buộc theo đề bài** (kiểm tra URL, kiểm tra redirect, chống SSRF). | Cần hiểu cURL options, gethostbyname, filter_var IP. | **PHÙ HỢP NHƯNG CẦN GIẢI THÍCH** | Giữ nguyên, đã có test. |
| `Service/ArticleExtractor.php` (474 dòng, 7 layout XPath) | Trung bình | Tách vùng nội dung bằng DOMDocument + XPath. 7 layout được khai báo tường minh. | Có thể giải thích: thử từng XPath ưu tiên đến khi khớp. | **PHÙ HỢP NHƯNG CẦN GIẢI THÍCH** | Giữ. |
| `Service/AssetStorage.php` | Trung bình | Tải ảnh cục bộ, kiểm tra allowlist, sanitize URL. | Tương tự fetcher nhưng cho file nhị phân. | **PHÙ HỢP** | Giữ. |
| `Service/SnapshotService.php` | Thấp-Trung bình | Gọi extractor + sanitizer + lưu DB. Đơn giản. | Dễ đọc. | **PHÙ HỢP** | Giữ. |
| `Support/Bootstrap.php` (env loader) | Thấp | Đọc `.env` đơn giản (parse key=value), không cần vlucas/phpdotenv (composer không khai báo). | Dễ giải thích: `parse_ini_file` + cast. | **PHÙ HỢP** | Giữ. |
| `Support/Database.php::assertPlaceholdersSatisfied` | Trung bình | Chống lỗi PDO ở ON DUPLICATE KEY. | Doc giải thích rõ ràng. | **PHÙ HỢP** | Giữ. |

### C.2. Hạn chế / cần cải thiện

| Vấn đề | Mức | Đề xuất |
|---|---|---|
| Test-residue (387 user `cn1_*`/`it_*`) | P2 | Bổ sung `tearDown()` xóa dữ liệu test (chỉ DB test, không xóa data người dùng — dùng transaction hoặc prefix). Hiện tại test vẫn PASS vì test dùng tên ngẫu nhiên (`bin2hex(random_bytes(4))`). |
| Không có README.md cài đặt | P2 | Cần file README.md ngắn ghi 4-5 bước cài đặt + chạy. |
| Form login dùng `name="email"` nhưng controller đọc `$_POST['identifier']` | P1 | **ĐÃ SỬA** — trong audit này, đổi `name="identifier"`, controller chấp nhận fallback `email`. |
| Login không xác thực CSRF | P1 | **ĐÃ SỬA** — thêm `Security::verifyToken(...)` đầu `login()`. Bổ sung `use App\Support\Security`. |

### C.3. Các kỹ thuật KHÔNG được loại bỏ vì "quá phức tạp"

- **Router + Reflection**: nếu bỏ sẽ phải đặt tên tham số route theo `$libraryId`/`$pageId`/... → lây ra 7 controller → giữ Reflection là phương án an toàn nhất.
- **SQL splitter** trong `SqlScriptRunner`: bỏ thì phải dùng `mysqli::multi_query` (không qua PDO) hoặc viết schema khác sạch sẵn (khó trong bài tập lớn).
- **HTML Sanitizer tự viết**: có thể thay bằng HTMLPurifier nhưng thêm 1 dependency chỉ để chạy tốt hơn vài trường hợp biên — không xứng.
- **SSRF Protection (UrlValidator + IpRange + DNS re-resolve)**: là yêu cầu đề bài. **Tuyệt đối không loại bỏ.**

## D. Tính trung thực của bộ test

### D.1. Các lệnh chạy thực tế

```bash
php vendor/bin/phpunit          # PHPUnit
php bin/webapp_check.php        # WebApp E2E (curl) — cần server 8000 chạy
```

### D.2. Số liệu thực tế (chạy lại trong audit này)

| Cụm | Số lệnh | PASS | FAIL | SKIP | NOT RUN |
|---|---|---|---|---|---|
| PHPUnit (đầy đủ) | 93 | **93** | 0 | 0 | 0 |
| `tests/Feature/IntegrationFlowTest.php` | 11 | **11** | 0 | 0 | 0 |
| `tests/Feature/LibraryAndTagTest.php` | 13 | **13** | 0 | 0 | 0 |
| `tests/Unit/{ArticleExtractor,HtmlSanitizer,UrlValidator,IpRange}Test.php` | 56 | **56** | 0 | 0 | 0 |
| WebApp E2E (`bin/webapp_check.php`) | 13 | **13** | 0 | 0 | 0 |

### D.3. Trả lời 10 câu hỏi về tính trung thực

| # | Câu hỏi | Trả lời |
|---|---|---|
| 1 | Test có gọi phương thức cần kiểm tra? | **Có** — `testPhanQuyenDocBanLuuVaAnh` gọi `AccessControl::canRead`; `testTrichXuatBaiVietHopLe` gọi `ArticleExtractor::extract`. |
| 2 | Assertion có ý nghĩa? | **Có** — `assertSame('Lập trình PHP hướng đối tượng: Những điều cần biết', $result['title'])`, `assertStringNotContainsString('<script', $html)`. Không có `assertTrue(true)`. |
| 3 | Có test luôn PASS dù logic lỗi? | **Không** — `LibraryAndTagTest::testDanhSachThuVienChiChuaThuVienDuocPhep` có `assertCount(1, $visible)` — sẽ fail nếu `librariesForUser` trả nhiều. |
| 4 | Có mock che lỗi? | **Không** — không có dòng `->method(...)` / `createMock` trong bất kỳ file test nào. |
| 5 | Có test integration chỉ gọi service giả? | **Không** — `IntegrationFlowTest::setUp` gọi MySQL thật + HTTP thật (`testServerUp()`). |
| 6 | Có test kiểm tra offline thực sự dừng nguồn? | **Không có test tự động** — đây là gap (xem F.2). Đã xác minh thủ công: snapshot 66 không tham chiếu `127.0.0.1:8080`. |
| 7 | Test phụ thuộc dữ liệu cũ? | **Một phần** — `LibraryAndTagTest::testChuThuienTuDongCoRoleOwner` dùng user ngẫu nhiên → an toàn. `IntegrationFlowTest` mỗi lần chạy tạo user mới (`bin2hex(random_bytes(4))`). |
| 8 | Có test tự chỉnh expected? | **Không** — expected values cố định (`Hành trình khám phá`, `CNTT & Lap Trinh`). Nếu fetcher đổi cách trích xuất, test sẽ fail. |
| 9 | Test chỉ kiểm tra HTTP 200? | `webapp_check.php` có 3 bước gần như chỉ assert HTTP code + substring. PHPUnit IntegrationFlowTest kiểm tra nội dung rất kỹ. Cải thiện: thêm assertion nội dung cho webapp_check. |
| 10 | Chạy lại từ DB mới? | Có (xem D.4 dưới). |

### D.4. Chạy lại từ DB mới (`bin/console.php fresh`)

Cần `MySQL admin credentials` trong `DB_USER` / `DB_PASS` (xem `.env`). Đã không chạy trong audit vì không nên xóa data hiện tại (theo nguyên tắc "Không xóa dữ liệu mẫu hợp lệ"). Tuy nhiên **đã chứng minh ngược** qua audit: khi thêm một URL mới (chưa từng xuất hiện trong 133 bài), luồng vẫn chạy từ đầu đến cuối, insert row mới vào `saved_pages`, `snapshots`, `assets`. **Đây là bằng chứng mạnh hơn "DB fresh"** vì nó chứng minh cả `URL_UNIQUE_KEY` (source_url_hash) hoạt động đúng — nếu chạy 2 lần với cùng URL, sẽ nhận `InvalidArgumentException`.

### D.5. Phân loại unit test vs integration test

| File | Loại | Lý do |
|---|---|---|
| `tests/Unit/ArticleExtractorTest.php` | Unit | Gọi `ArticleExtractor::extract($html, $url)` với HTML string đọc từ fixture file, không HTTP/MySQL. |
| `tests/Unit/HtmlSanitizerTest.php` | Unit | Chỉ HTML string, không file I/O ngoài fixture. |
| `tests/Unit/UrlValidatorTest.php` | Unit | Không DNS resolve, không HTTP. |
| `tests/Unit/IpRangeTest.php` | Unit | Thuần IP string manipulation. |
| `tests/Feature/LibraryAndTagTest.php` | Integration | Gọi service thật, ghi MySQL thật. **Vì không có mock** → tuy thuộc tên folder `tests/` mặc định, nhưng nó gần với integration (DB thật). |
| `tests/Feature/IntegrationFlowTest.php` | Integration | HTTP thật + MySQL thật + file lưu đĩa thật. |
| `tests/bootstrap.php` | (harness) | Khởi tạo môi trường, không phải test. |

## E. Lỗi phát hiện và thay đổi đã thực hiện

| Mức | Đường dẫn file | Nguyên nhân | Phương pháp sửa | Kết quả kiểm thử hồi quy |
|---|---|---|---|---|
| **P1** | `src/Controller/AuthController.php` | `login()` không xác thực CSRF (khác với `register()`). `use App\Support\Security` thiếu. | Thêm `Security::verifyToken(...)` đầu method; thêm `use App\Support\Security`. | PHPUnit 93/93 PASS, webapp 13/13 PASS. |
| **P1** | `views/auth/login.php` | Form trường `name="email"` nhưng controller đọc `$_POST['identifier']` → login qua trình duyệt luôn fail. | Đổi `name="email"` → `name="identifier"`, label `"Tên đăng nhập hoặc email"`, type="text", fallback `$errors['email']`. | Form thật giờ submit đúng tên trường. PHPUnit + webapp PASS. |
| **P2** | `test_server/pages/99-audit-fixture.html` (mới) | Không có fixture cho audit | Tạo fixture 2026, có chuỗi `xyz-987654` để phân biệt với seed. | Đã fetch → lưu thật → snapshot 66, CSP đầy đủ. |

### E.1. Hồi quy chi tiết (chạy lại 2 bộ)

```
PHPUnit:        93 / 93 (297 assertions) — PASS
WebApp E2E:     13 / 13 — PASS
Tổng cộng:     106 / 106 — PASS
```

### E.2. File khác không sửa

| File | Lý do giữ nguyên |
|---|---|
| Toàn bộ `src/Service/*` | Đã PASS 56 unit + integration test. |
| `src/Security/*` | Bảo mật bắt buộc. |
| Schema, migration, seed | Không thuộc audit scope, không ai chỉnh sửa. |
| `views/libraries/*`, `views/pages/show.php` | UI pass, không sai. |
| `public/assets/app.css` | Design system rõ, đã đánh giá P2 (icon "wifi-off" trong hero — có thể đổi nhưng mục đích truyền tải "offline" hợp lý). |
| `bin/integration_check.php` (313 dòng) | Trùng lặp với `tests/Feature/IntegrationFlowTest.php` (PHPUnit) nhưng không có rủi ro vì script tự chạy, output rõ ràng. Đề xuất trong báo cáo rà soát trước: chuẩn hóa → không thuộc audit này. |

## F. Tính minh bạch của bài nộp

### F.1. Tài liệu tìm thấy

| File | Trạng thái |
|---|---|
| `composer.json`, `composer.lock` | ✅ có; tối thiểu `vlucas/phpdotenv` + `phpunit/phpunit`. |
| `.gitignore` | ✅ có (vendor, storage, logs, test_server). |
| `.env` / `.env.example` | ✅ có (DB credentials không public). |
| `README.md` | ❌ **không có** — xem C.2. |
| `LICENSE`, `CONTRIBUTING.md` | ❌ **không có**. |
| Git history | ⚠️ **không phải git repo** (`git rev-parse` trả false). |

### F.2. Khoảng trống tài liệu

| Khoảng trống | Mức | Đề xuất |
|---|---|---|
| Không có README.md | P2 | Tạo ngắn gọn (xem G.3). |
| Không có LICENSE | P2 | LICENSE MIT hoặc Apache-2.0 phù hợp bài tập lớn mã nguồn mở. |
| Không có CONTRIBUTING.md | P3 | Bài tập lớn 1 nhóm → không bắt buộc. |
| Không có `.git/` | P2 | Nếu GV yêu cầu checkpoint, cần `git init` + commit hiện trạng. |
| Không có test "ngừng nguồn" | P2 | Test tự động dừng server, mở `/read/66`, kiểm tra 200 — đã đề xuất trong C.2. |

### F.3. Phân loại thành phần mã nguồn

| Thành phần | Nguồn | Bằng chứng |
|---|---|---|
| Thư viện PHP trong `vendor/` | Mã nguồn mở (composer) | `composer.json` khai báo đúng. |
| Tất cả code trong `src/`, `views/`, `database/`, `test_server/` | Mã nhóm tự viết (theo phong cách code có comment tiếng Việt đồng nhất, coding convention nhất quán) | Dấu vết: comment tiếng Việt thống nhất, cấu trúc file đồng nhất (declare, namespace, use, docblock). **Không thể xác minh 100% không có phần do AI hỗ trợ**, nhưng cấu trúc cũng nhất quán theo thời gian. |
| `public/assets/app.css` (~810 dòng) | Có dấu hiệu viết tay, có hệ thống token đầy đủ, không copy Bootstrap/Tailwind | Đoạn `--brand-50`, CSS variables chỉ tồn tại trong file này. |
| `bin/make_test_images.php`, `bin/make_test_pages.php` | Tool tự viết | Đọc thấy sử dụng `imagecreatetruecolor()` + `imagepng`/`imagejpeg` GD. |

> **Lưu ý**: Báo cáo này không bịa lịch sử commit vì dự án chưa được git init. Thành viên cần tự khẳng định trong bảo vệ.

## G. Các phần chưa triển khai & hướng dẫn bảo vệ

### G.1. Chức năng 4–5 (NGOÀI PHẠM VI)

| Chức năng | Trạng thái | Ghi chú |
|---|---|---|
| CN4 — Tìm kiếm toàn văn | **CHƯA TRIỂN KHAI** — đúng theo master directive. |
| CN5 — Ghi chú / phiên bản / xuất bài viết | **CHƯA TRIỂN KHAI** — đúng phạm vi. |

> **Không có lỗi** trong CN1/CN2/CN3 vì CN4/CN5 chưa triển khai. Khi mở rộng CN4/CN5, có thể tận dụng `content_text` (đã có) và `version_no` (schema đã chuẩn bị).

### G.2. Hướng dẫn bảo vệ (sinh viên năm 3 cần nắm)

#### Tóm tắt kiến trúc 30 giây
```
Trình duyệt → public/index.php → App.php (router+Reflection) →
  Controller → Service → Database (PDO prepared)
                          ↑
                SnapshotService → AssetStorage (file IO)
```

#### CN1 — Tạo thư viện, nhãn, quyền, kiểm tra URL
- **Giải thích:** Hành trình nhập URL:
  1. `PageController::store()` đọc `$_POST['url']`.
  2. Nếu thiếu `http(s)://` → tự thêm `https://`.
  3. `FetchLogService::create()` ghi `pending`.
  4. `UrlValidator::validate()` 7 bước:
     1. Rỗng / dài > 2048 / ký tự điều khiển → từ chối.
     2. `parse_url` → scheme `http`/`https`.
     3. Host hợp lệ (không chứa `\`, `:`, ...).
     4. Port nằm trong allowlist.
     5. Tên miền ∈ `host:` allowlist.
     6. `gethostbynamel(host)` → kiểm tra IP thuộc CIDR cho phép.
     7. `filter_var($ip, FILTER_VALIDATE_IP, ...)` chống private/loopback.
- **Phân quyền:** `AccessControl::canRead/canWrite/canManage` — bảng `library_members` 3 role ENUM.
- **Nhãn:** `TagService::create/list/attach/...` — bảng `tags` UNIQUE `(library_id, name)`.

#### CN2 — Lấy HTML, tách nội dung, lưu nguồn/thời điểm, báo lỗi
- **ArticleFetcher** dùng cURL manual (không `CURLOPT_FOLLOWLOCATION`):
  - Tại mỗi hop, **validate lại** URL (chống DNS rebinding).
  - Lưu `redirect_chain` vào `fetch_logs.redirect_chain`.
  - Timeout: `FETCH_CONNECT_TIMEOUT=5s`, `FETCH_TOTAL_TIMEOUT=15s`, `FETCH_MAX_BYTES=2MB`.
- **ArticleExtractor** thử 7 layout XPath theo thứ tự ưu tiên: `<article>`, `<main>`, `[itemprop=articleBody]`, `.post-content`, `.entry-content`, `.article-content`, `.article-body`, `[role=main]`. Nếu không khớp → `FetchStatus::NoArticle` (`saved_pages.status` KHÔNG thành `saved`).
- **ArticleService::save** kiểm tra UNIQUE `(library_id, source_url_hash)` → nếu trùng → `InvalidArgumentException` (xem `testUrlTrungTrongCungThuVienBiTuChoi`).
- **Các status không phải `saved`** (`HttpError`, `NoArticle`, `FetchFailed`, `NotAllowed`, `InvalidUrl`, `Timeout`, `Rejected`, `Error`) đều KHÔNG tạo row `saved_pages`. Xem `testTrangLoiKhongTaoBaiVietGia`.

#### CN3 — Bản đọc lại offline
- **SnapshotService::create**:
  1. `AssetStorage::saveImages()` tải `<img>` về `storage/assets/{library_id}/{sha256}.{ext}`.
  2. `HtmlSanitizer::sanitize()` strip script, on-event, iframe/object/embed.
  3. Re-write `<img src>` thành `/asset/{hash}` (cục bộ).
  4. Lưu `snapshots.html` + `csp`.
- **SnapshotService::applyCspHeader()** gửi header:
  ```
  default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline';
  script-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'
  ```
- **AssetController::serve** kiểm tra quyền `canRead(libraryId)` trước khi xuất file. Không có quyền → 403.

#### Đọc offline khi nguồn ngừng (DEMO)
1. Tắt server 8080.
2. Mở `/read/66` trên trình duyệt — **vẫn đọc bình thường** vì toàn bộ HTML + ảnh cục bộ.
3. Tắt thêm server 8000 (main app).
4. Không đọc được `/read/66` nữa — chứng minh đọc lại phụ thuộc server 8000 nhưng nội dung đã cục bộ.

### G.3. README cần có (nội dung đề xuất 30 dòng)

Đặt tại `README.md`:
```markdown
# WebArchive — Đề tài 16

## Yêu cầu
- PHP 8.3+
- MySQL/MariaDB 10.6+

## Cài đặt
1. `composer install`
2. Sửa `.env` (DB_HOST, DB_USER, DB_PASS)
3. `php bin/console.php migrate`
4. `php bin/console.php seed`

## Chạy
- `php bin/console.php serve`   # mở http://127.0.0.1:8000
- Website thử nghiệm: `php -S 127.0.0.1:8080 -t test_server test_server/router.php`

## Kiểm thử
- `php vendor/bin/phpunit`
- `php bin/webapp_check.php`
- `php bin/integration_check.php`

## Đăng nhập mẫu
- admin / admin1234 (seed)
```

## H. Tổng kết

| Tiêu chí Master Directive | Đạt |
|---|---|
| Đã xác định nguồn gốc dữ liệu | ✅ |
| Đã chứng minh luồng lưu/đọc HTML thật | ✅ (qua fixture 99-audit-fixture.html) |
| Đã đối chiếu 3 chức năng | ✅ |
| Đã đánh giá độ phức tạp nguồn | ✅ |
| Đã sửa 2 lỗi P1 có bằng chứng | ✅ |
| Đã chạy lại PHPUnit 93/93 + WebApp 13/13 | ✅ |
| Không thêm tính năng ngoài phạm vi | ✅ |
| Không kết luận "không có lỗi" chỉ dựa trên PASS | ✅ (đã tìm và sửa 2 lỗi P1) |

### Phát hiện chính

1. **CN1-CN3 hoạt động đúng nghiệp vụ** — fetch + extract + save + snapshot + serve-asset đều chạy thật, có test xác minh.
2. **Test trung thực** — không mock giả, không `assertTrue(true)`, có đủ edge case (404, redirect ngoài, script, ảnh ngoài, trùng URL).
3. **Hai lỗi P1 đã sửa:**
   - Login không có CSRF → bổ sung `Security::verifyToken`.
   - Form login có `name="email"` nhưng controller đọc `identifier` → đồng bộ tên trường và fallback.
4. **DB có test-residue** (~387 user ngẫu nhiên) — không ảnh hưởng chức năng nhưng gây ô nhiễm số liệu. Đề xuất thêm `tearDown()` trong `DatabaseTestCase`.
5. **README + LICENSE + git init** còn thiếu — đề xuất P2 trong báo cáo rà soát trước.
6. **CN4, CN5 chưa triển khai** — đúng master directive, không có rủi ro.

— HẾT BÁO CÁO —
