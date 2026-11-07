<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use App\Support\Db;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB as LaravelDB;
use Throwable;

/** Tiến trình con cho ca kiểm thử đặt lịch đồng thời (SV-25) */
class MoonsBookWorker extends Command
{
    protected $signature = 'moons:book-worker {nhanKhau} {tienIch} {date} {start} {--db=}';

    protected $description = 'Tiến trình đặt tiện ích dùng trong kiểm thử đồng thời';

    public function handle(): int
    {
        if ($db = $this->option('db')) {
            config(['database.connections.' . config('database.default') . '.database' => $db]);
            LaravelDB::purge();
        }
        $me = Db::one("SELECT nk.*, ch.ma_can_ho FROM nhan_khau nk JOIN can_ho ch ON ch.ID_can_ho=nk.ID_can_ho WHERE ID_nhan_khau=?", [(int) $this->argument('nhanKhau')]);
        BookingService::$testDelayUs = 400000;   // giữ khóa 0,4 giây để hai giao dịch chắc chắn chồng lấn
        try {
            $r = BookingService::book($me, (int) $this->argument('tienIch'), $this->argument('date'), $this->argument('start'), 4);
            $this->output->write("OK {$r['code']}");
        } catch (Throwable $e) {
            $this->output->write('ERR ' . $e->getMessage());
        }
        return 0;
    }
}
