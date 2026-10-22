# Đóng góp cho DeTai16 — WebArchive

Cảm ơn bạn đã quan tâm tới dự án. Tài liệu này mô tả cách thiết lập môi trường phát triển,
quy tắc commit và quy trình cập nhật tiến độ.

---

## 1. Yêu cầu trước khi bắt đầu

| Phần mềm | Phiên bản |
|---|---|
| PHP | 8.2 trở lên |
| MySQL / MariaDB | 5.7 / 10.4 |
| Composer | 2.x |

PHP cần các extension: `curl`, `dom`, `libxml`, `mbstring`, `pdo_mysql`, `fileinfo`, `json`.

---

## 2. Thiết lập môi trường

```powershell
git clone https://github.com/doansensei1503-maker/DeTai16-WebArchive.git
cd DeTai16-WebArchive

composer install
Copy-Item .env.example .env
```

Sửa thông tin database trong `.env` cho khớp máy của bạn:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=detai16
DB_USER=<user-cua-ban>
DB_PASS=<mat-khau-cua-ban>
```

Sau đó tạo bảng và dữ liệu mẫu:

```powershell
php bin/console.php migrate
php bin/console.php seed
```

Chạy ứng dụng:

```powershell
.\start.ps1 -Fixtures
```

Mở <http://127.0.0.1:8000> và đăng nhập bằng `admin` / `admin1234`.

---

## 3. Quy tắc không được vi phạm

### 3.1. Tuyệt đối không commit bí mật

Các file sau **không được** đưa lên Git:

| Loại | Ví dụ |
|---|---|
| File cấu hình có mật khẩu | `.env` |
| Thư mục cài lại qua Composer | `vendor/` |
| Ảnh người dùng đã tải về | `storage/assets/` |
| Log chạy máy chủ | `storage/logs/`, `logs_server*.txt` |
| Cache | `storage/cache/` |

`.gitignore` đã cấu hình sẵn các quy tắc trên. Nếu vô tình commit `.env`,
**không dùng thao tác xóa lịch sử để che giấu** — hãy báo ngay cho nhóm trưởng để
đánh giá mức độ thiệt hại.

### 3.2. Không tự ý đổi phạm vi nghiệp vụ

- Không sửa migration đã áp dụng. Muốn đổi schema thì tạo file mới trong `database/`.
- Không thay đổi hành vi của `HtmlSanitizer`, `UrlValidator`, cơ chế phân quyền
  nếu không gắn với một yêu cầu cụ thể.
- Không thêm chức năng ngoài phạm vi đề tài (xem `docs/PROGRESS.md`).

### 3.3. Trang đọc offline phải giữ độc lập

`views/read/show.php` là bản HTML độc lập, **không** được:

- Thêm `<link>` tới CDN hay font bên ngoài.
- Thêm `<script>` bất kỳ (CSP đang khoá `script-src 'none'`).
- Thêm CSS từ `public/assets/app.css`.

Mọi CSS của trang này phải nằm trong thẻ `<style>` ngay trong file.

---

## 4. Chạy kiểm thử trước khi commit

```powershell
php vendor/bin/phpunit
```

Bộ E2E (cần web app đang chạy ở cổng 8000):

```powershell
.\start.ps1
php bin/webapp_check.php
```

Yêu cầu trước khi tạo commit:

- PHPUnit không có test FAIL.
- `webapp_check.php` báo `FAIL: 0`.

Nếu sửa phần liên quan đến thu thập nội dung, cần bật máy chủ thử nghiệm:

```powershell
.\start.ps1 -Fixtures
```

---

## 5. Quy tắc commit

### 5.1. Định dạng thông điệp

Dùng `<loại>: <mô tả ngắn>` với `<loại>` trong danh sách sau:

| Loại | Dùng khi |
|---|---|
| `feat` | Thêm tính năng mới |
| `fix` | Sửa lỗi |
| `test` | Thêm hoặc sửa kiểm thử |
| `style` | Chỉ thay đổi giao diện, không đổi logic |
| `docs` | Chỉ thay đổi tài liệu |
| `refactor` | Sửa lại cấu trúc, không đổi hành vi |
| `chore` | Việc phụ: cấu hình, công cụ |

### 5.2. Ví dụ

```
feat: hoàn thiện quản lý thư viện
feat: lưu nội dung HTML và trích xuất bài viết
feat: hỗ trợ đọc lại offline
fix: chặn redirect ra ngoài phạm vi khi thu thập
test: bổ sung kiểm thử phân quyền thư viện
style: cải thiện giao diện theo bảng màu navy và warm white
docs: cập nhật tiến độ sau khi hoàn thành CN3
```

### 5.3. Nguyên tắc

- **Một commit làm một việc.** Không gộp sửa lỗi với thêm tính năng.
- **Không tách nhỏ để làm đẹp số liệu.** Không cố tình chia thành nhiều commit.
- **Không sửa ngày giờ commit** và không tạo lịch sử giả.
- **Không dùng `git push --force`.** Lịch sử commit là bằng chứng tiến độ học tập.

---

## 6. Quy trình hoàn thành một chức năng

Khi làm xong một chức năng hoặc một nhóm sửa lỗi:

1. Chạy kiểm thử, xác nhận không có FAIL.
2. Cập nhật `docs/PROGRESS.md` — ghi kết quả chạy **thực tế vừa chạy**.
3. `git add` chỉ những file liên quan.
4. Kiểm tra không có bí mật trong phần sắp commit:
   ```powershell
   git diff --cached
   ```
5. Tạo commit với thông điệp mô tả đúng nội dung.
6. Push lên nhánh `main`:
   ```powershell
   git push origin main
   ```
7. Cập nhật Issue trên GitHub nếu issue đó đang mở.

Chỉ ghi "Đã hoàn thành" trong `docs/PROGRESS.md` khi đã có test tương ứng chạy PASS.

---

## 7. Báo cáo lỗi

Khi phát hiện lỗi, mô tả:

1. Bạn làm gì dẫn đến lỗi.
2. Điều gì xảy ra thực tế.
3. Điều gì bạn mong đợi.
4. Ảnh chụp màn hình hoặc thông báo lỗi trong `logs_server_err.txt`.

---

## 8. Đặt câu hỏi

Mở Issue với nhãn `question` trên GitHub.