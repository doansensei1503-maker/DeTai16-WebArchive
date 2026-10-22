# ĐỀ TÀI 16 — Ứng dụng lưu nội dung trang Web để đọc lại và tìm kiếm

**Tên dự án:** WebArchive — Thư viện Web cá nhân
**Môn học:** Phát triển phần mềm — Bài tập lớn
**Ngành:** Phát triển phần mềm (Sinh viên năm 3)

---

## 1. Mục tiêu đề tài

Xây dựng ứng dụng cho phép người dùng **lưu lại nội dung của một trang Web** về máy chủ
của mình, tổ chức thành thư viện, và **đọc lại được khi trang gốc đã không còn hoạt động**.

Bài toán bản chất: nội dung Web thay đổi và biến mất theo thời gian. Ứng dụng tạo ra một
bản đọc **độc lập hoàn toàn** — không phụ thuộc trang gốc, không phụ thuộc tài nguyên bên ngoài.

---

## 2. Giới thiệu ứng dụng

### Luồng sử dụng chính

```
Người dùng đăng nhập
        ↓
Tạo / chọn một thư viện (ví dụ: "CNTT & Lập Trình")
        ↓
Dán URL bài viết vào biểu mẫu "Lưu trang Web mới"
        ↓
Hệ thống: kiểm tra URL → tải trang → trích nội dung chính → làm sạch HTML → tải ảnh về máy chủ
        ↓
Bài viết xuất hiện trong danh sách (kèm tiêu đề, nguồn, thời gian lưu, nhãn, trạng thái)
        ↓
Bấm "Đọc lại" → mở bản đọc offline, không cần mạng
```

### Ba tính năng cốt lõi đã hoàn thành

| Mã | Tính năng |
|---|---|
| **CN1** | Quản lý thư viện, nhãn, phân quyền 3 vai trò (Chủ sở hữu / Biên tập viên / Người xem) và lưu URL |
| **CN2** | Lấy HTML từ URL và trích xuất nội dung chính của bài viết |
| **CN3** | Đọc lại offline, làm sạch HTML chống XSS, lưu ảnh về máy chủ |

Tiến độ chi tiết từng tính năng: **[`docs/PROGRESS.md`](docs/PROGRESS.md)**

---

## 3. Công nghệ sử dụng

| Thành phần | Công nghệ | Ghi chú |
|---|---|---|
| Ngôn ngữ chính | PHP 8.2+ | Không dùng framework, viết thuần |
| Cơ sở dữ liệu | MySQL / MariaDB | PDO + prepared statement |
| Giao diện | HTML5, CSS3, JavaScript thuần | Không framework frontend |
| Tải trang | cURL | Giới hạn byte, timeout, số lần redirect |
| Phân tích HTML | DOM + libxml | Tách tiêu đề / thân bài / tác giả / ngày đăng |
| Kiểm thử | PHPUnit 10.5 | Unit test + Feature test |
| Máy chủ phát triển | PHP built-in server | Qua `start.ps1` |

**Phụ thuộc Composer:** chỉ `phpunit/phpunit` (dev). Runtime không có thư viện ngoài.

---

## 4. Phạm vi đề tài

Theo tài liệu đề tài gồm 5 chức năng:

| Mã | Nội dung | Trạng thái |
|---|---|---|
| CN1 | Quản lý thư viện, nhãn, quyền truy cập, URL | ✅ Đã hoàn thành |
| CN2 | Lấy HTML và trích xuất nội dung | ✅ Đã hoàn thành |
| CN3 | Đọc lại offline, làm sạch HTML, lưu ảnh | ✅ Đã hoàn thành |
| CN4 | Tìm kiếm và ghi chú | ⬜ Chưa triển khai |
| CN5 | Phiên bản và xuất bài viết | ⬜ Chưa triển khai |

> Dự án hiện tập trung hoàn thiện **3 chức năng đầu** theo phạm vi giao việc.
> Chi tiết phần chưa làm nằm ở `docs/PROGRESS.md`.

---

## 5. Cấu trúc thư mục

```
DeTai16/
├── bin/
│   ├── console.php            # Lệnh CLI: migrate, seed, fresh
│   ├── webapp_check.php       # Kiểm thử E2E qua HTTP thật
│   └── integration_check.php  # Kiểm thử tích hợp
├── database/
│   └── 001_schema.sql         # Toàn bộ schema MySQL
├── docs/
│   ├── PROGRESS.md            # ⭐ Bảng tiến độ công khai
│   └── AUDIT_DETAI16.md       # Báo cáo kiểm toán mã nguồn
├── public/
│   ├── index.php              # Entry point của web app
│   ├── router.php             # Router cho PHP built-in server
│   └── assets/app.css         # Toàn bộ giao diện (design token + component)
├── src/
│   ├── App.php                # Front controller + bảng định tuyến
│   ├── Controller/            # Xử lý request
│   ├── Domain/                # Model: User, LibraryRole, FetchStatus
│   ├── Security/              # HtmlSanitizer, UrlValidator, IpRange, AllowList
│   ├── Service/               # Logic nghiệp vụ
│   └── Support/               # Database, Auth, Env, Bootstrap, Security
├── storage/                   # ⚠ KHÔNG commit — ảnh đã lưu, log, cache
├── test_server/               # Máy chủ HTML tĩnh 15 trang fixture để thử thu thập
├── tests/
│   ├── Unit/                  # Test đơn vị
│   ├── Feature/               # Test tích hợp
│   └── DatabaseTestCase.php
├── views/                     # Template PHP
│   ├── layout.php             # Layout dùng chung
│   ├── home.php
│   ├── auth/                  # login, register
│   ├── libraries/             # index, show, members
│   ├── pages/                 # index, show
│   └── read/show.php          # ⭐ Trang đọc offline (HTML độc lập)
├── .env.example               # Mẫu cấu hình (KHÔNG chứa mật khẩu thật)
├── composer.json
├── phpunit.xml
└── start.ps1                  # Khởi động ứng dụng trên Windows
```

---

## 6. Yêu cầu cài đặt

| Phần mềm | Phiên bản tối thiểu |
|---|---|
| PHP | 8.2 |
| MySQL / MariaDB | 5.7 / 10.4 |
| Composer | 2.x |

PHP cần các extension: `curl`, `dom`, `libxml`, `mbstring`, `pdo_mysql`, `fileinfo`, `json`.

Kiểm tra nhanh:

```powershell
php -v
php -m
composer --version
```

---

## 7. Cài đặt

### Bước 1 — Tải mã nguồn

```powershell
git clone https://github.com/doansensei1503-maker/DeTai16-WebArchive.git
cd DeTai16-WebArchive
```

### Bước 2 — Cài thư viện

```powershell
composer install
```

### Bước 3 — Tạo file cấu hình

```powershell
Copy-Item .env.example .env
```

Mở `.env` và sửa phần database cho khớp máy của bạn:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=detai16
DB_USER=<user-mysql-cua-ban>
DB_PASS=<mat-khau-mysql-cua-ban>
```

> ⚠️ File `.env` chứa mật khẩu nên **không được commit** lên GitHub.
> File này đã nằm trong `.gitignore`.

### Bước 4 — Tạo cơ sở dữ liệu và bảng

Lệnh `migrate` sẽ tự tạo database nếu chưa có:

```powershell
php bin/console.php migrate
```

Script schema nằm ở `database/001_schema.sql`. Lệnh trên:

1. `CREATE DATABASE IF NOT EXISTS detai16`
2. Chạy các câu lệnh trong `001_schema.sql`
3. In ra số câu lệnh đã thực thi

### Bước 5 — Tạo dữ liệu mẫu

```powershell
php bin/console.php seed
```

Tạo 5 tài khoản, 3 thư viện và một số nhãn để dùng thử:

| Tài khoản | Mật khẩu |
|---|---|
| `admin` | `admin1234` |
| `thanhvien` | `tv123456` |
| `khanhnt` | `khanh123` |
| `hieupt` | `hieu123` |
| `namnh` | `nam123` |

> Đây là tài khoản **demo** dùng cho môi trường học tập.
> Khi triển khai thật phải đổi toàn bộ mật khẩu.

Muốn xóa sạch rồi tạo lại từ đầu:

```powershell
php bin/console.php fresh
```

### Bước 6 — Chạy ứng dụng

```powershell
.\start.ps1
```

Mở trình duyệt: <http://127.0.0.1:8000>

Đăng nhập bằng `admin` / `admin1234`.

### Các lệnh của `start.ps1`

| Lệnh | Tác dụng |
|---|---|
| `.\start.ps1` | Chạy web app ở cổng 8000 |
| `.\start.ps1 -Port 8080` | Đổi cổng |
| `.\start.ps1 -Fixtures` | Chạy thêm máy chủ thử nghiệm ở cổng 8080 (cần khi lưu URL) |
| `.\start.ps1 -Foreground` | Chạy hiện trên cửa sổ PowerShell, dừng bằng `Ctrl+C` |

> **Lưu ý:** tính năng lưu URL chỉ thu thập được trong danh sách cho phép
> (`ALLOWED_FETCH_TARGETS` trong `.env`, mặc định trỏ tới máy chủ thử nghiệm 8080).
> Hãy chạy `-Fixtures` để có nguồn trang để lưu thử.

### Chạy thủ công (không dùng `start.ps1`)

```powershell
# Máy chủ thử nghiệm
php -S 127.0.0.1:8080 -t test_server test_server/router.php

# Web app
php -S 127.0.0.1:8000 -t public public/router.php
```

---

## 8. Chạy kiểm thử

### Bộ test PHPUnit

```powershell
composer test
# hoặc
php vendor/bin/phpunit
```

### Kiểm thử E2E qua HTTP

Cần server đang chạy ở cổng 8000:

```powershell
.\start.ps1
php bin/webapp_check.php
```

### Kết quả hiện tại

| Bộ kiểm thử | Kết quả |
|---|---|
| PHPUnit | OK — 93 test, 297 assertions |
| WebApp E2E | 13 PASS / 0 FAIL |

Bao phủ: làm sạch HTML và chặn XSS · chống SSRF · trích xuất nội dung ·
phân quyền thư viện · lưu và đọc lại bài · phục vụ ảnh nội bộ · đăng nhập và CSRF.

---

## 9. Thành viên nhóm

> Chưa được cung cấp. Bổ sung tại đây trước khi nộp bài.

| STK | Họ và tên | Vai trò | Phần việc |
|---|---|---|---|
| — | — | — | — |

---

## 10. Giấy phép và nguồn

**Mã nguồn:** MIT — xem [`LICENSE`](LICENSE).

### Thành phần bên thứ ba

| Thành phần | Giấy phép | Vai trò |
|---|---|---|
| PHPUnit | BSD-3-Clause | Chỉ dùng khi phát triển, không đưa vào runtime |

Ứng dụng **không** dùng CDN, không tải font hay thư viện JavaScript từ bên ngoài.
Toàn bộ giao diện dùng system font và CSS tự viết trong `public/assets/app.css`,
đảm bảo trang đọc offline hoạt động khi không có kết nối mạng.

### Ghi chú bảo mật

Trang đọc offline phục vụ kèm CSP `default-src 'none'; script-src 'none'` và nội dung
đã qua `HtmlSanitizer`. Chi tiết kiểm toán tại [`docs/AUDIT_DETAI16.md`](docs/AUDIT_DETAI16.md).

---

## 11. Đóng góp

Xem [`CONTRIBUTING.md`](CONTRIBUTING.md).

---

## 12. Tài liệu liên quan

| Tài liệu | Nội dung |
|---|---|
| [`docs/PROGRESS.md`](docs/PROGRESS.md) | Tiến độ từng chức năng, có minh chứng |
| [`docs/AUDIT_DETAI16.md`](docs/AUDIT_DETAI16.md) | Báo cáo kiểm toán mã nguồn và dữ liệu |
| [`CONTRIBUTING.md`](CONTRIBUTING.md) | Quy trình đóng góp và commit |
| [`.env.example`](.env.example) | Mẫu cấu hình môi trường |