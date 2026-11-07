<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Sinh dữ liệu mô phỏng: 4 tháp, 1.285 căn hộ, cư dân, thẻ xe, ~12.800 hóa đơn, giao dịch,
 * lịch tiện ích, phiếu sự cố và tài liệu ngữ cảnh AI. Kết quả cố định (mt_srand) để kiểm thử lặp lại được.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        require __DIR__ . '/moons_seed.php';
    }
}
