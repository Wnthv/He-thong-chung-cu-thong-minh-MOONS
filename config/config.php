<?php
/**
 * Cấu hình hệ thống Moons. Sao chép thành config.local.php để ghi đè khi triển khai.
 */
$config = [
    'app_name' => 'Chung cư Moons',
    'timezone' => 'Asia/Ho_Chi_Minh',
    // Ngày hệ thống dùng cho bản demo (null = ngày thực tế của máy chủ)
    'fixed_today' => null,
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => getenv('MOONS_DB') ?: 'moons',
        'user' => 'moons',
        'pass' => 'moons123',
    ],
    'upload_max_bytes' => 5 * 1024 * 1024,
    // VNPay: để trống tmn_code => chạy cổng VNPay MÔ PHỎNG nội bộ (cùng thuật toán ký HMAC SHA512)
    'vnpay' => [
        'tmn_code'    => '',
        'hash_secret' => 'MOONSDEMOSECRETKEY2026HMACSHA512',
        'pay_url'     => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        'version'     => '2.1.0',
    ],
    // Google Gemini: để trống api_key => Trợ lý AI chạy chế độ tra cứu nội quy (không gọi mô hình)
    'gemini' => [
        'api_key' => '',
        'model'   => 'gemini-2.5-flash',
        'timeout' => 5,
    ],
    // OTP: chưa tích hợp nhà mạng SMS => hiển thị mã OTP trên màn hình (chế độ demo)
    'otp_demo' => true,
    'otp_ttl'  => 60,
];
if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_replace_recursive($config, require __DIR__ . '/config.local.php');
}
return $config;
