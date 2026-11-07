<?php

/*
|--------------------------------------------------------------------------
| Cấu hình nghiệp vụ hệ thống Chung cư Moons
|--------------------------------------------------------------------------
| Đọc từ file .env để không phải sửa mã nguồn khi triển khai.
*/

return [
    'app_name' => 'Chung cư Moons',

    // Ngày hệ thống dùng cho bản demo (để trống = ngày thực tế của máy chủ)
    'fixed_today' => env('MOONS_FIXED_TODAY') ?: null,

    'upload_max_bytes' => 5 * 1024 * 1024,

    // VNPay: để trống VNPAY_TMN_CODE => chạy cổng VNPay MÔ PHỎNG nội bộ (cùng thuật toán ký HMAC SHA512)
    'vnpay' => [
        'tmn_code'    => (string) env('VNPAY_TMN_CODE', ''),
        'hash_secret' => (string) env('VNPAY_HASH_SECRET', 'MOONSDEMOSECRETKEY2026HMACSHA512'),
        'pay_url'     => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'version'     => '2.1.0',
    ],

    // Google Gemini: để trống GEMINI_API_KEY => Trợ lý AI chạy chế độ tra cứu nội quy (không gọi mô hình)
    'gemini' => [
        'api_key' => (string) env('GEMINI_API_KEY', ''),
        'model'   => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'timeout' => 5,
    ],

    // OTP: chưa tích hợp nhà mạng SMS => hiển thị mã OTP trên màn hình (chế độ demo)
    'otp_demo' => (bool) env('OTP_DEMO', true),
    'otp_ttl'  => 60,
];
