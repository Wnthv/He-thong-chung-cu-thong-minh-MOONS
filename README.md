# Hệ thống quản lý cư dân & dịch vụ tiện ích – Chung cư Moons (Laravel)

[![CI](https://github.com/Wnthv/He-thong-chung-cu-thong-minh-MOONS/actions/workflows/ci.yml/badge.svg)](https://github.com/Wnthv/He-thong-chung-cu-thong-minh-MOONS/actions/workflows/ci.yml)

**Laravel 13** (PHP ≥ 8.3) + MySQL 8 / MariaDB 10.6+. Chạy được trên WAMP/XAMPP (Windows), GitHub Codespaces và GitHub Actions.

## 1. Cài đặt trên máy Windows (WAMP)

### Chuẩn bị (làm 1 lần)
1. **WAMP**: bật WAMP (biểu tượng màu xanh). MariaDB mặc định chạy cổng 3306, tài khoản `root`, không mật khẩu.
2. **PHP 8.3 cho dòng lệnh**: dùng PHP có sẵn trong WAMP, ví dụ `C:\wamp64\bin\php\php8.3.14`.
   Thêm đường dẫn này vào biến môi trường **Path** của Windows (Start → *Edit the system environment variables* → *Environment Variables* → *Path* → *New*).
   Mở CMD mới, gõ `php -v` phải thấy `PHP 8.3.x`.
3. **Bật extension** trong file `C:\wamp64\bin\php\php8.3.14\php.ini` (bỏ dấu `;` đầu dòng):
   `extension=curl`, `extension=fileinfo`, `extension=gd`, `extension=intl`, `extension=mbstring`, `extension=openssl`, `extension=pdo_mysql`, `extension=zip`.
4. **Composer**: tải và cài từ https://getcomposer.org/download/ (Composer-Setup.exe), khi được hỏi đường dẫn PHP thì chọn `php.exe` ở bước 2.

### Cài dự án
```bat
cd /d D:\He-thong-chung-cu-thong-minh-MOONS
composer install
copy .env.example .env
php artisan key:generate
```
Tạo CSDL rỗng tên **`moons`** (bảng mã `utf8mb4_unicode_ci`) trong phpMyAdmin (`http://localhost/phpmyadmin`), rồi:
```bat
php artisan migrate --seed
php artisan serve
```
Mở **http://127.0.0.1:8000**. Bước `--seed` mất khoảng 40–60 giây (sinh 1.285 căn hộ, ~12.800 hóa đơn).

> Muốn tạo lại dữ liệu từ đầu: `php artisan migrate:fresh --seed`.
>
> **Cách khác (không cần chạy migrate):** trong phpMyAdmin chọn CSDL `moons` → **Import** → chọn tệp `database/moons.sql.gz` → **Import**.
> Tệp này là bản xuất của CSDL sau `migrate --seed` (15 bảng, 26 khóa ngoại, 16 trigger, dữ liệu mẫu và bảng `migrations`).
> Nếu MariaDB có mật khẩu, sửa `DB_PASSWORD` trong file `.env`.

## 2. Tài khoản thử (mật khẩu `Moons@123`)
| Vai trò | Tài khoản |
|---|---|
| Chủ hộ | `0981230001` |
| Khách thuê | `0981230002` |
| Admin | `trilq` |
| Kế toán | `toannk` |
| Kế toán trưởng | `hapt` |
| Lễ tân | `tantl` |
| Điều phối viên | `dieuvm` |
| Kỹ thuật viên | `namhv` |

## 3. Cấu trúc mã nguồn (Model – View – Controller)
| Thành phần | Thư mục |
|---|---|
| **Model** – 15 Model Eloquent ↔ 15 bảng, đủ 26 quan hệ khóa ngoại | `app/Models/` |
| **View** – giao diện Blade (phân hệ Cư dân, Ban Quản lý) | `resources/views/` |
| **Controller** – Auth, Resident, Payment, Admin | `app/Http/Controllers/` |
| Định tuyến + phân quyền theo vai trò | `routes/web.php`, `app/Http/Middleware/RoleMiddleware.php` |
| Tầng nghiệp vụ (Service) | `app/Services/` (13 service: hóa đơn, VNPay, tiện ích, sự cố, AI…) |
| Lược đồ CSDL: 15 bảng, 26 FK, CHECK, cột sinh, 16 trigger | `database/migrations/` (16 tệp) |
| Dữ liệu mô phỏng | `database/seeders/` |
| Cấu hình nghiệp vụ | `config/moons.php` (đọc từ `.env`) |
| Kiểm thử tự động | `tests/run_tests.php`, lệnh `php artisan moons:test` |

Lược đồ do migration tạo ra trùng khớp với thiết kế CSDL trong báo cáo (mục 3.3): 15 bảng, 178 cột, 60 chỉ mục, 26 khóa ngoại, 42 ràng buộc CHECK, 16 trigger.

### Chạy bằng Apache của WAMP (tùy chọn, thay cho `php artisan serve`)
1. Chuột trái biểu tượng WAMP → PHP → Version → chọn **8.3.x**.
2. Mở `http://localhost` → **Add a Virtual Host**: tên `moons.local`, đường dẫn `D:/He-thong-chung-cu-thong-minh-MOONS/public`.
3. Chuột phải biểu tượng WAMP → Tools → **Restart DNS**, rồi mở `http://moons.local`.

## 4. Tích hợp tùy chọn (file `.env`)
- **VNPay**: điền `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`; để trống thì dùng cổng VNPay mô phỏng (cùng thuật toán HMAC SHA512 + IPN). IPN URL: `.../vnpay/ipn`.
- **Gemini**: điền `GEMINI_API_KEY`; để trống thì Trợ lý AI chạy chế độ tra cứu nội quy.
- **OTP**: `OTP_DEMO=true` hiển thị mã trên màn hình (chưa tích hợp SMS).
- **Ngày hệ thống**: `MOONS_FIXED_TODAY=2026-11-04` cố định ngày khớp bộ dữ liệu trong báo cáo; để trống để dùng ngày thực.

## 5. Kiểm thử tự động (75 ca)
Chạy tự động trên **GitHub Actions** mỗi lần `git push` (tab *Actions*, file kết quả ở mục *Artifacts*).
Chạy trên máy: tạo CSDL `moons_test` là bản sao của `moons` sau khi seed (phpMyAdmin → *Operations* → *Copy database to*), rồi:
```bat
php artisan moons:test --db=moons_test
```
Nhóm PQ (phân quyền qua HTTP) cần thêm máy chủ web chạy trên CSDL `moons_test` và tham số `--http=http://127.0.0.1:8080`.
Kết quả lần chạy cuối: `tests/test_output.txt`, `tests/results.json`.

Giá điện trong dữ liệu mẫu theo QĐ 1279/QĐ-BCT (2025) – cần đối chiếu biểu giá hiện hành.
