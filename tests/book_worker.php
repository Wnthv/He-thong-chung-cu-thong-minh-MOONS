<?php
/* Tiến trình con cho kiểm thử đặt lịch đồng thời: php book_worker.php <ID_nhan_khau> <ID_tien_ich> <date> <start> */
require __DIR__ . '/../app/bootstrap.php';
$me = DB::one("SELECT nk.*, ch.ma_can_ho FROM nhan_khau nk JOIN can_ho ch ON ch.ID_can_ho=nk.ID_can_ho WHERE ID_nhan_khau=?", [(int)$argv[1]]);
BookingService::$testDelayUs = 400000;   // giữ khóa 0,4 giây để hai giao dịch chắc chắn chồng lấn
try { $r = BookingService::book($me, (int)$argv[2], $argv[3], $argv[4], 4); echo "OK {$r['code']}"; }
catch (Throwable $e) { echo 'ERR ' . $e->getMessage(); }
