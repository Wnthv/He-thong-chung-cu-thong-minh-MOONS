# Hệ thống quản lý cư dân & dịch vụ tiện ích – Chung cư Moons (MVP)

[![CI](https://github.com/Wnthv/He-thong-chung-cu-thong-minh-MOONS/actions/workflows/ci.yml/badge.svg)](https://github.com/Wnthv/He-thong-chung-cu-thong-minh-MOONS/actions/workflows/ci.yml)

PHP 8 (MVC tự xây dựng) + MySQL 8.0.16+/MariaDB 10.4+. Chạy được trên WAMP/XAMPP, không cần Composer.

## Cài đặt
1. Bật các extension PHP: `pdo_mysql`, `gd`, `intl`, `curl`, `mbstring`.
2. Tạo CSDL `moons` (utf8mb4) → import `database/schema.sql` (phpMyAdmin hỗ trợ `DELIMITER`).
3. Sửa thông tin kết nối trong `config/config.php` (hoặc tạo `config/config.local.php`).
4. Sinh dữ liệu mô phỏng: `php database/seed.php` (~30 giây; 1.285 căn hộ, ~12.800 hóa đơn).
5. Trỏ web root tới `public/` rồi mở `index.php`. Máy chủ thử nhanh: `cd public && php -S 127.0.0.1:8080`.

## Tài khoản thử (mật khẩu `Moons@123`)
Chủ hộ `0981230001` · Khách thuê `0981230002` · Admin `trilq` · Kế toán `toannk` · Kế toán trưởng `hapt` · Lễ tân `tantl` · Điều phối viên `dieuvm` · Kỹ thuật viên `namhv`

## Tích hợp tùy chọn
- VNPay: điền `vnpay.tmn_code` và `vnpay.hash_secret`; để trống thì dùng cổng VNPay mô phỏng (cùng thuật toán HMAC SHA512 + IPN). Khai báo IPN URL: `.../index.php?r=vnpay/ipn`.
- Gemini: điền `gemini.api_key`; để trống thì Trợ lý AI chạy chế độ tra cứu nội quy.
- OTP: `otp_demo = true` hiển thị mã trên màn hình (chưa tích hợp SMS).

## Kiểm thử tự động (75 ca)
```
mysqldump moons > seed.sql && mysql -e "CREATE DATABASE moons_test" && mysql moons_test < seed.sql
MOONS_DB=moons_test php tests/run_tests.php --http=http://127.0.0.1:8080/index.php
```
Kết quả lần chạy cuối: `tests/test_output.txt`, `tests/results.json`.
Giá điện trong `database/seed.php` theo QĐ 1279/QĐ-BCT (2025) – cần đối chiếu biểu giá hiện hành.

## Chạy kiểm thử trên GitHub Actions
Mỗi lần `git push` lên nhánh `main` (hoặc mở Pull Request), workflow `.github/workflows/ci.yml` sẽ tự động:
kiểm tra cú pháp PHP → dựng MySQL 8 → import `schema.sql` → chạy `seed.php` (ngày hệ thống cố định `2026-11-04`) → sao chép sang `moons_test` → bật `php -S` → chạy đủ 75 ca kiểm thử (cả nhóm PQ qua HTTP).
Xem kết quả ở tab **Actions**; file `results.json` được đính kèm ở mục *Artifacts* của mỗi lần chạy.
Có thể chạy tay bằng nút **Run workflow** (workflow_dispatch).

> `config/config.local.php` và thư mục `public/uploads/` đã được đưa vào `.gitignore` để không lộ mật khẩu/API key và ảnh tải lên.
