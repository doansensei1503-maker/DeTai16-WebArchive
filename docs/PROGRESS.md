# TIẾN ĐỘ PHÁT TRIỂN — ĐỀ TÀI 16 (WebArchive)

> **Nguyên tắc của file này:** mọi trạng thái dưới đây đều được đối chiếu với mã nguồn
> và kết quả chạy kiểm thử thực tế. Không có phần trăm hoàn thành tự đặt,
> không có mốc thời gian bịa, không tính PASS theo báo cáo cũ.
>
> **Cập nhật gần nhất:** 2026-10-22
> **Căn cứ kiểm chứng:** mã nguồn trong `src/`, bộ test trong `tests/`,
> kết quả chạy `vendor/bin/phpunit` và `bin/webapp_check.php`.

---

## 1. Bảng trạng thái tổng quan

| Chức năng | Trạng thái | Minh chứng |
|---|---|---|
| **CN1** — Quản lý thư viện, nhãn, quyền truy cập và URL | **Đã hoàn thành** | `src/Controller/LibraryController.php`, `TagController.php`, `LibraryMemberController.php`; 13 test trong `tests/Feature/LibraryAndTagTest.php` PASS |
| **CN2** — Lấy HTML và trích xuất nội dung | **Đã hoàn thành** | `src/Service/ArticleFetcher.php`, `ArticleExtractor.php`; 14 test trong `tests/Unit/ArticleExtractorTest.php` PASS |
| **CN3** — Đọc lại offline, làm sạch HTML, lưu ảnh | **Đã hoàn thành** | `src/Security/HtmlSanitizer.php`, `SnapshotService.php`, `AssetStorage.php`, `views/read/show.php`; 16 test trong `tests/Unit/HtmlSanitizerTest.php` + `IntegrationFlowTest.php` PASS |
| **CN4** — Tìm kiếm và ghi chú | **Chưa triển khai** | Không có route/controller/service nào. `UserService::searchByUsername()` chỉ dùng cho ô "thêm thành viên", không phải tìm kiếm bài viết |
| **CN5** — Phiên bản và xuất bài viết | **Chưa triển khai** | Không có route/controller/service nào; bảng `snapshots` lưu 1 bản đọc/bài, không có lịch sử phiên bản hay xuất file |

---

## 2. CN1 — Quản lý thư viện, nhãn, quyền truy cập và URL

**Trạng thái:** Đã hoàn thành
**Cập nhật:** 2026-10-22

### Mục tiêu
Người dùng đăng nhập tạo và quản lý nhiều thư viện riêng, gắn nhãn phân loại cho bài
viết, mời cộng tác viên với ba vai trò (Chủ sở hữu / Biên tập viên / Người xem) và lưu
URL bài viết vào thư viện.

### Đã hoàn thành
| Hạng mục | Minh chứng trong mã nguồn |
|---|---|
| Đăng ký / đăng nhập / đăng xuất, mật khẩu băm bcrypt | `AuthController.php`, `Auth.php` |
| CRUD thư viện (tạo, xem, sửa tên, xóa kèm cascading) | `LibraryController.php`, `LibraryService.php` |
| Ba vai trò và ma trận phân quyền | `src/Domain/LibraryRole.php`, `src/Service/AccessControl.php` |
| Quản lý thành viên: thêm, đổi vai trò, xóa | `LibraryMemberController.php` |
| Nhãn: tạo, đổi tên, xóa, gắn vào bài viết | `TagController.php`, `TagService.php` |
| Nhãn không tràn sang thư viện khác | `testNhanKhongDuocTranSangThuVienKhac()` |
| Chặn SSRF trước khi thu thập URL | `src/Security/UrlValidator.php`, `IpRange.php`, `AllowList.php` |
| Lưu URL vào thư viện qua form | `PageController::store()` |

### Còn thiếu
- Chưa có phân trang danh sách bài viết khi thư viện lớn.
- Chưa có tìm kiến nhãn theo tên.

### Kết quả kiểm thử
```
tests/Feature/LibraryAndTagTest.php  — 13 test PASS
tests/Unit/UrlValidatorTest.php      — PASS
tests/Unit/IpRangeTest.php           — PASS
```

---

## 3. CN2 — Lấy HTML và trích xuất nội dung

**Trạng thái:** Đã hoàn thành
**Cập nhật:** 2026-10-22

### Mục tiêu
Tải trang từ URL người dùng nhập, xác định phần nội dung chính và tách riêng tiêu đề,
thân bài, tác giả, ngày đăng, ảnh và mô tả.

### Đã hoàn thành
| Hạng mục | Minh chứng trong mã nguồn |
|---|---|
| Tải trang bằng cURL, giới hạn byte / timeout / redirect | `ArticleFetcher.php` |
| Kiểm tra URL trước khi tải (chống SSRF) | `UrlValidator.php` |
| Phân tích DOM, loại bỏ thành phần phụ (nav, footer, sidebar) | `ArticleExtractor.php` |
| Phân biệt tiêu đề và thân bài, trích tác giả / ngày đăng | `ArticleExtractor.php` |
| Từ chối trang 404 và trang không có nội dung hợp lệ | `testTrang404BiTuChoi()`, `testTrangKhongCoNoiDungHopLeBiTuChoi()` |
| Từ chối bài quá ngắn | `testBaiVietQuaNganBiTuChoi()` |
| Không gộp nhầm hai bài cùng tiêu đề | `testHaiBaiCungTieuDeKhongBiGopNham()` |
| Giữ nguyên tiếng Việt có dấu | `testUnicodeTiengVietDuocGiuNguyen()` |
| Ghi nhật ký thu thập, báo trạng thái qua API | `FetchLogService.php`, `ApiController.php` |

### Còn thiếu
- Chưa hỗ trợ nội dung trang cần JavaScript để hiển thị.
- Chưa có thử lại tự động khi thu thập thất bại.

### Kết quả kiểm thử
```
tests/Unit/ArticleExtractorTest.php — 14 test PASS
tests/Feature/IntegrationFlowTest.php — test thu thap that voi fixture PASS
```

---

## 4. CN3 — Đọc lại offline, làm sạch HTML, lưu ảnh

**Trạng thái:** Đã hoàn thành
**Cập nhật:** 2026-10-22

### Mục tiêu
Lưu bản đọc độc lập hoàn toàn: HTML đã làm sạch, ảnh tải về máy chủ, và trang đọc
không phụ thuộc bất kỳ tài nguyên bên ngoài nào.

### Đã hoàn thành
| Hạng mục | Minh chứng trong mã nguồn |
|---|---|
| Loại bỏ `<script>`, `<iframe>`, `<object>`, `<embed>` | `HtmlSanitizer.php` |
| Loại bỏ thuộc tính sự kiện (`onclick`, `onerror`, ...) | `testThuocTinhSuKienBiLoaiBo()` |
| Chặn `javascript:` và `data:` URL | `testJavaScriptUrlBiLoaiBo()`, `testDataUrlBiLoaiBo()` |
| Chặn `<link>`, `<style>`, thuộc tính `style` inline | `testStyleVaLinkBiLoaiBo()` |
| Ảnh tải về máy chủ, thay bằng đường dẫn nội bộ `/asset/{hash}` | `AssetStorage.php` |
| Chỉ nhận MIME ảnh an toàn (png/jpeg/webp) | `IMAGE_ALLOWED_MIME` trong `.env.example` |
| Từ chối phục vụ ảnh ngoài phạm vi lưu trữ | `testAssetStorageTuChoiAnhNgoaiPhamVi()` |
| Trang đọc là HTML độc lập, CSS nội tuyến, không tải tài nguyên ngoài | `views/read/show.php` |
| Header CSP khoá `default-src 'none'; script-src 'none'` | `Security::headers()` |
| Link ra ngoài luôn có `rel="noopener"` | `testLinkNgoaiDuocThemNoopener()` |

### Còn thiếu
- Chưa có nén ảnh lại trước khi lưu.
- Chưa có tùy chọn xuất toàn bộ thư viện ra file HTML để đọc ngoại tuyến.

### Kết quả kiểm thử
```
tests/Unit/HtmlSanitizerTest.php   — 16 test PASS
tests/Feature/IntegrationFlowTest.php — 10 test PASS
  gồm: testBanDocLaiKhongChayScriptVaKhongTaiTaiNguonBenNgoai()
       testAnhKhongDuocLuuThiKhongTaiLaiTuNguon()
       testPhanQuyenDocBanLuuVaAnh()
```

---

## 5. CN4 — Tìm kiếm và ghi chú

**Trạng thái:** Chưa triển khai
**Cập nhật:** 2026-10-22

### Mục tiêu (theo tài liệu đề tài)
Cho phép tìm kiếm nội dung đã lưu và ghi chú cá nhân lên bài viết.

### Hiện trạng thực tế
- Không có route `/search` trong `src/App.php`.
- Không có controller/service xử lý tìm kiếm nội dung bài viết.
- Không có cột hoặc bảng lưu ghi chú.
- `UserService::searchByUsername()` là chức năng phụ trợ cho ô "thêm thành viên",
  **không phải** tìm kiếm bài viết, không tính vào CN4.

### Việc cần làm
- [ ] Thiết kế bảng `notes` và migration.
- [ ] Xây dựng chỉ mục tìm kiếm trên tiêu đề / thân bài / nhãn.
- [ ] Trang kết quả tìm kiếm có phân trang.
- [ ] Biểu mẫu thêm / sửa / xóa ghi chú trên trang chi tiết bài viết.
- [ ] Test cho phân quyền ghi chú.

---

## 6. CN5 — Phiên bản và xuất bài viết

**Trạng thái:** Chưa triển khai
**Cập nhật:** 2026-10-22

### Mục tiêu (theo tài liệu đề tài)
Lưu nhiều phiên bản của cùng một bài viết và cho phép xuất bài ra định dạng khác.

### Hiện trạng thực tế
- Bảng `snapshots` lưu **một** bản đọc cho mỗi bài đã lưu, không phải lịch sử phiên bản.
- Không có route xuất file (`/export`, `/download`).
- Không có cơ chế so sánh hai phiên bản.

### Việc cần làm
- [ ] Bảng `page_versions` + migration.
- [ ] Ghi lại phiên bản mới mỗi khi thu thập lại cùng một URL.
- [ ] Chức năng xuất PDF / Markdown / HTML.
- [ ] Test tính toàn vẹn bản xuất.

---

## 7. Kết quả kiểm thử tổng thể

Lệnh chạy:
```powershell
php vendor/bin/phpunit
php bin/webapp_check.php
```

| Bộ kiểm thử | Kết quả |
|---|---|
| PHPUnit (`tests/Unit` + `tests/Feature`) | **OK — 93 test, 297 assertions** |
| WebApp E2E (`bin/webapp_check.php`) | **Tổng 13 \| PASS 13 \| FAIL 0** |

Bộ E2E kiểm tra các luồng thật qua HTTP: đăng nhập có CSRF, phân quyền thư viện,
lưu URL, chặn SSRF, phục vụ ảnh nội bộ, 404, đăng xuất.

---

## 8. GitHub Issues và Milestones

**Trạng thái:** Chưa tạo trên GitHub.
**Nguyên nhân:** máy đang cấu hình chưa có GitHub CLI (`gh`) và chưa xác thực
GitHub API bằng token có quyền quản lý issue.

### Cách cấu quyền để tạo Issue và Milestone

1. Cài GitHub CLI:
   ```powershell
   winget install --id GitHub.cli
   ```
2. Đăng nhập bằng trình duyệt (không dán token vào màn hình lệnh):
   ```powershell
   gh auth login
   ```
   Chọn: `GitHub.com` → `HTTPS` → `Login with a web browser`.
3. Kiểm tra đã đăng nhập:
   ```powershell
   gh auth status
   ```

### Cấu trúc milestone dự kiến

| Milestone | Nội dung | Trạng thái |
|---|---|---|
| **M1 — CN1 đến CN3** | Quản lý thư viện, thu thập nội dung, đọc offline | Đã hoàn thành |
| **M2 — CN4** | Tìm kiếm và ghi chú | Chưa bắt đầu |
| **M3 — CN5** | Phiên bản và xuất bài viết | Chưa bắt đầu |
| **M4 — Bàn giao** | Kiểm thử tổng thể, đóng gói, báo cáo | Đang thực hiện |

Sau khi có `gh`, tạo nhanh:

```powershell
gh milestone create "M1 — CN1-CN3" --description "..."
gh issue create --title "..." --milestone "M1 — CN1-CN3" --label enhancement
```

Trong lúc chờ cấp quyền, các mục việc CN4 và CN5 đã được ghi đầy đủ trong
mục 5 và mục 6 của file này.

---

## 9. Cách cập nhật file này

Mỗi khi hoàn thành một chức năng hoặc một nhóm sửa lỗi:

1. Chạy `php vendor/bin/phpunit` và `php bin/webapp_check.php`.
2. Cập nhật bảng ở mục 1 và mục chi tiết của chức năng tương ứng.
3. Ghi kết quả kiểm thử **thực tế vừa chạy**, không dùng kết quả cũ.
4. Commit kèm thay đổi: `docs: cập nhật tiến độ <nội dung>`.

Không ghi trạng thái "Đã hoàn thành" nếu chưa có test tương ứng chạy PASS.