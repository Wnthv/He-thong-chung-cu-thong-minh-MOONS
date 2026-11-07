<?php
/* Sao chép thành config.local.php rồi sửa cho máy của bạn (XAMPP mặc định: root, không mật khẩu) */
return [
    'db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'moons', 'user' => 'root', 'pass' => ''],
    // 'fixed_today' => '2026-11-04',   // bật nếu muốn chạy bộ kiểm thử với đúng ngày của bộ dữ liệu trong báo cáo
    // 'gemini' => ['api_key' => 'AIza...'],
];
