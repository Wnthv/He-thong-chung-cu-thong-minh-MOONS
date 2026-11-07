<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Chạy 75 ca kiểm thử tự động (nhóm DB, SV, PQ) trên CSDL kiểm thử riêng.
 *   php artisan moons:test --db=moons_test --http=http://127.0.0.1:8080
 * Kết quả ghi ra tests/results.json.
 */
class MoonsTest extends Command
{
    protected $signature = 'moons:test {--db=moons_test : CSDL kiểm thử (bản sao của CSDL đã seed)} {--http= : Địa chỉ máy chủ web để chạy nhóm PQ}';

    protected $description = 'Chạy bộ kiểm thử tự động của hệ thống Moons';

    public function handle(): int
    {
        if ($db = $this->option('db')) {
            config(['database.connections.' . config('database.default') . '.database' => $db]);
            DB::purge();
        }
        $httpBase = $this->option('http') ? rtrim($this->option('http'), '/') : null;
        return require base_path('tests/run_tests.php');
    }
}
