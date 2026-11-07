<?php
/**
 * Kịch bản sinh dữ liệu mô phỏng cho Chung cư Moons (được gọi từ DatabaseSeeder).
 * Chạy: php artisan migrate:fresh --seed
 * Mọi bản ghi đều đi qua đầy đủ CHECK/UNIQUE/FK/TRIGGER của CSDL.
 */

use App\Services\AuditService;
use App\Services\BillingService;
use App\Services\FeeService;
use App\Services\ResidentService;
use App\Support\Db as DB;

mt_srand(2026);
$T0 = microtime(true);
$pdo = DB::pdo();
$TODAY = app_today();
$PW = password_hash('Moons@123', PASSWORD_BCRYPT);
echo "Ngày hệ thống: $TODAY\n";

function ins(string $table, array $row): int
{
    $cols = implode(',', array_keys($row));
    $q = implode(',', array_fill(0, count($row), '?'));
    DB::q("INSERT INTO $table ($cols) VALUES ($q)", array_values($row));
    return DB::id();
}
function pick(array $a) { return $a[mt_rand(0, count($a) - 1)]; }
function chance(float $p): bool { return mt_rand() / mt_getrandmax() < $p; }

/* ---------- Ảnh mẫu (đặt trong public/uploads/seed) ---------- */
$up = public_path('uploads/seed');
if (!is_dir($up)) mkdir($up, 0775, true);
function img(string $file, array $bg, string $title, string $sub): void
{
    $im = imagecreatetruecolor(640, 400);
    imagefill($im, 0, 0, imagecolorallocate($im, ...$bg));
    $w = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 30, 30, 610, 370, imagecolorallocatealpha($im, 255, 255, 255, 90));
    imagerectangle($im, 30, 30, 610, 370, $w);
    imagestring($im, 5, 50, 60, $title, $w);
    imagestring($im, 3, 50, 100, $sub, $w);
    for ($i = 0; $i < 6; $i++) imagefilledrectangle($im, 50, 150 + $i * 30, 50 + mt_rand(250, 520), 160 + $i * 30, $w);
    imagejpeg($im, $file, 80);
}
img("$up/ca_vet.jpg", [30, 80, 160], 'GIAY CHUNG NHAN DANG KY XE', 'Vehicle registration certificate (sample)');
img("$up/hien_truong.jpg", [110, 110, 105], 'ANH HIEN TRUONG SU CO', 'Damage photo (sample)');
img("$up/nghiem_thu.jpg", [40, 120, 80], 'ANH NGHIEM THU SAU SUA CHUA', 'Completed repair photo (sample)');
img("$up/cccd.jpg", [70, 90, 130], 'CAN CUOC CONG DAN - HAI MAT', 'ID card (sample)');
img("$up/hop_dong.jpg", [120, 90, 60], 'HOP DONG THUE CAN HO', 'Lease contract (sample)');

/* ---------- Tài khoản nhân viên ---------- */
$pdo->beginTransaction();
$staff = [
    ['trilq', 'Lê Quản Trị', 'admin'], ['toannk', 'Nguyễn Kế Toán', 'ke_toan'], ['hapt', 'Phạm Thu Hà', 'ke_toan_truong'],
    ['tantl', 'Trần Lễ Tân', 'le_tan'], ['dieuvm', 'Võ Minh Điều', 'dieu_phoi_vien'], ['namhv', 'Hứa Văn Nam', 'ky_thuat_vien'],
    ['huongpt', 'Phạm Thanh Hương', 'ky_thuat_vien'], ['phucdh', 'Đặng Hữu Phúc', 'ky_thuat_vien'],
];
$S = [];
foreach ($staff as $i => [$u, $n, $r]) {
    $S[$u] = ins('tai_khoan', ['ten_dang_nhap' => $u, 'mat_khau_bam' => $PW, 'ho_ten' => $n, 'email' => "$u@moons.vn",
        'so_dien_thoai' => sprintf('09000%05d', 7919 + $i * 7919 % 99999), 'vai_tro' => $r, 'ngay_tao' => '2022-06-01 08:00:00']);
}
$KT = $S['toannk']; $KTT = $S['hapt']; $LT = $S['tantl']; $ADM = $S['trilq']; $DPV = $S['dieuvm'];
$TECHS = [$S['namhv'], $S['huongpt'], $S['phucdh']];

/* ---------- Biểu phí (giá điện bậc thang: Quyết định 1279/QĐ-BCT ngày 09/5/2025 – cần đối chiếu biểu giá hiện hành) ---------- */
$fees = [
    ['DIEN_B1', 'Điện sinh hoạt bậc 1', 0, 50, 1984, 'kWh'], ['DIEN_B2', 'Điện sinh hoạt bậc 2', 51, 100, 2050, 'kWh'],
    ['DIEN_B3', 'Điện sinh hoạt bậc 3', 101, 200, 2380, 'kWh'], ['DIEN_B4', 'Điện sinh hoạt bậc 4', 201, 300, 2998, 'kWh'],
    ['DIEN_B5', 'Điện sinh hoạt bậc 5', 301, 400, 3350, 'kWh'], ['DIEN_B6', 'Điện sinh hoạt bậc 6', 401, null, 3460, 'kWh'],
    ['NUOC', 'Nước sinh hoạt', null, null, 15000, 'm³'], ['QUAN_LY', 'Phí quản lý', null, null, 12000, 'm²/tháng'],
    ['XE_O_TO', 'Phí gửi ô tô', null, null, 1200000, 'xe/tháng'], ['XE_MAY', 'Phí gửi xe máy', null, null, 150000, 'xe/tháng'],
];
foreach ($fees as [$c, $n, $a, $b, $p, $u]) ins('bieu_phi', ['ma_phi' => $c, 'ten_phi' => $n, 'tu_muc' => $a, 'den_muc' => $b,
    'don_gia' => $p, 'don_vi' => $u, 'ngay_cap_nhat' => '2025-05-10 08:00:00', 'ID_nguoi_cap_nhat' => $KTT]);

/* ---------- 1.285 căn hộ: 27 shophouse tầng 1 + 1.258 căn hộ tầng 2–28 ---------- */
$quota = ['A' => [7, 315], 'B' => [7, 315], 'C' => [7, 314], 'D' => [6, 314]];
$areas = [65, 75, 85, 95, 105, 55];
$APT = [];
foreach ($quota as $t => [$sh, $n]) {
    for ($u = 1; $u <= $sh; $u++) $APT[] = ins('can_ho', ['ma_can_ho' => sprintf('%s-01%02d', $t, $u), 'thap_nha' => $t, 'so_tang' => 1,
        'loai_can' => 'shophouse', 'dien_tich' => 120 + 10 * ($u % 4)]);
    $left = $n;
    for ($f = 2; $f <= 28 && $left > 0; $f++) {
        $per = $left > (28 - $f) * 11 + 11 ? 12 : 11;
        for ($u = 1; $u <= $per && $left > 0; $u++, $left--) {
            $APT[] = ins('can_ho', ['ma_can_ho' => sprintf('%s-%02d%02d', $t, $f, $u), 'thap_nha' => $t, 'so_tang' => $f, 'dien_tich' => $areas[($u - 1) % 6]]);
        }
    }
}
echo 'Căn hộ: ' . count($APT) . "\n";

/* ---------- Cư dân ---------- */
$ho = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương', 'Lý', 'Hứa', 'Trương'];
$dem = ['Văn', 'Thị', 'Minh', 'Thanh', 'Quốc', 'Ngọc', 'Hữu', 'Đức', 'Thu', 'Bích', 'Gia', 'Khánh', 'Hoài', 'Phương'];
$ten = ['An', 'Bảo', 'Châu', 'Dũng', 'Hà', 'Hải', 'Hương', 'Hoa', 'Khôi', 'Lan', 'Long', 'Mai', 'Nam', 'Ngọc', 'Phúc', 'Quang', 'Sơn', 'Tâm', 'Thảo', 'Trang', 'Tuấn', 'Vy', 'Yến', 'Linh', 'Hùng'];
$name = fn() => pick($ho) . ' ' . pick($dem) . ' ' . pick($ten);
$cccdN = 79190000000;
$cccd = function () use (&$cccdN) { $cccdN += mt_rand(7, 977); return sprintf('%012d', $cccdN); };
$phoneN = 81230010;
$phone = function () use (&$phoneN) { $phoneN += mt_rand(1, 37); return '09' . $phoneN; };

$OCC = [];   // ID_can_ho => [owner_nk, tenant_nk|null, primary_nk, people]
$apts = DB::all("SELECT * FROM can_ho ORDER BY ID_can_ho");
foreach ($apts as $a) {
    $special = in_array($a['ma_can_ho'], ['A-1001', 'A-1002'], true);
    $r = mt_rand() / mt_getrandmax();
    if (!$special && $r < 0.15) continue;                                    // 15% căn trống
    $rented = $a['ma_can_ho'] === 'A-1002' || (!$special && $r > 0.85);
    // Chủ hộ (luôn có tài khoản)
    if ($a['ma_can_ho'] === 'A-1001') { $on = 'Hứa Vân Thanh'; $op = '0981230001'; $oc = '079203000123'; $mail = 'thanh.hv@moons.vn'; }
    elseif ($a['ma_can_ho'] === 'A-1002') { $on = 'Đỗ Minh Tâm'; $op = '0981230003'; $oc = $cccd(); $mail = null; }
    else { $on = $name(); $op = $phone(); $oc = $cccd(); $mail = null; }
    $uid = ins('tai_khoan', ['ten_dang_nhap' => $op, 'mat_khau_bam' => $PW, 'ho_ten' => $on, 'email' => $mail, 'so_dien_thoai' => $op,
        'vai_tro' => 'chu_ho', 'ngay_tao' => '2022-07-01 09:00:00']);
    $owner = ins('nhan_khau', ['ho_ten' => $on, 'ngay_sinh' => sprintf('%d-%02d-%02d', mt_rand(1965, 1995), mt_rand(1, 12), mt_rand(1, 28)),
        'gioi_tinh' => pick(['nam', 'nu']), 'so_dien_thoai' => $op, 'email' => $mail, 'so_cccd' => $oc, 'quan_he_chu_ho' => 'Chủ hộ',
        'loai_cu_dan' => 'chu_ho', 'anh_giay_to' => 'seed/cccd.jpg', 'trang_thai_duyet' => 'da_duyet', 'ngay_duyet' => '2022-07-02 10:00:00',
        'ID_can_ho' => $a['ID_can_ho'], 'ID_tai_khoan' => $uid, 'ID_nguoi_duyet' => $LT]);
    $tenant = null;
    $people = $rented ? 0 : 1;
    if ($rented) {
        if ($a['ma_can_ho'] === 'A-1002') { $tn = 'Đỗ Bích Ngọc'; $tp = '0981230002'; $start = '2026-06-01'; $end = '2027-05-31'; }
        else { $tn = $name(); $tp = $phone(); $start = date('Y-m-01', strtotime($TODAY . ' -' . mt_rand(2, 20) . ' months')); $end = date('Y-m-t', strtotime("$start +" . pick([12, 18, 24]) . ' months -1 day')); }
        $tuid = ins('tai_khoan', ['ten_dang_nhap' => $tp, 'mat_khau_bam' => $PW, 'ho_ten' => $tn, 'so_dien_thoai' => $tp, 'vai_tro' => 'khach_thue', 'ngay_tao' => "$start 09:00:00"]);
        $tenant = ins('nhan_khau', ['ho_ten' => $tn, 'ngay_sinh' => sprintf('%d-%02d-%02d', mt_rand(1975, 2000), mt_rand(1, 12), mt_rand(1, 28)),
            'gioi_tinh' => pick(['nam', 'nu']), 'so_dien_thoai' => $tp, 'so_cccd' => $cccd(), 'quan_he_chu_ho' => 'Khách thuê', 'loai_cu_dan' => 'khach_thue',
            'so_hop_dong' => 'HDT-' . str_replace('-', '', $a['ma_can_ho']) . '-' . substr($start, 0, 4), 'ngay_bat_dau_hop_dong' => $start, 'ngay_ket_thuc_hop_dong' => $end,
            'anh_giay_to' => 'seed/hop_dong.jpg', 'trang_thai_duyet' => 'da_duyet', 'ngay_duyet' => "$start 10:00:00",
            'ID_can_ho' => $a['ID_can_ho'], 'ID_tai_khoan' => $tuid, 'ID_nguoi_duyet' => $LT]);
        $people = 1;
    }
    // Thành viên hộ gia đình
    if ($a['ma_can_ho'] === 'A-1001') {
        ins('nhan_khau', ['ho_ten' => 'Nguyễn Văn Hoàng', 'ngay_sinh' => '1990-04-12', 'gioi_tinh' => 'nam', 'so_cccd' => '079204000456',
            'quan_he_chu_ho' => 'Vợ/Chồng', 'loai_cu_dan' => 'thanh_vien', 'anh_giay_to' => 'seed/cccd.jpg', 'trang_thai_duyet' => 'da_duyet',
            'ngay_duyet' => '2022-07-02 10:00:00', 'ID_can_ho' => $a['ID_can_ho'], 'ID_nguoi_duyet' => $LT]);
        ins('nhan_khau', ['ho_ten' => 'Hứa Gia Bảo', 'ngay_sinh' => '2018-09-05', 'gioi_tinh' => 'nam', 'quan_he_chu_ho' => 'Con',
            'loai_cu_dan' => 'thanh_vien', 'ID_can_ho' => $a['ID_can_ho']]);
        $people = 2;
    } else {
        $nm = $a['loai_can'] === 'shophouse' ? mt_rand(0, 1) : mt_rand(1, 3);
        for ($i = 0; $i < $nm; $i++) {
            $child = $i > 0 && chance(0.5);
            $pending = chance(0.012);
            ins('nhan_khau', ['ho_ten' => $name(), 'ngay_sinh' => $child ? sprintf('%d-%02d-15', mt_rand(2012, 2022), mt_rand(1, 12)) : sprintf('%d-%02d-10', mt_rand(1960, 2000), mt_rand(1, 12)),
                'gioi_tinh' => pick(['nam', 'nu']), 'so_cccd' => $child ? null : $cccd(), 'quan_he_chu_ho' => $child ? 'Con' : pick(['Vợ/Chồng', 'Cha/Mẹ', 'Anh/Chị/Em']),
                'loai_cu_dan' => 'thanh_vien', 'anh_giay_to' => $child ? null : 'seed/cccd.jpg', 'trang_thai_duyet' => $pending ? 'cho_duyet' : 'da_duyet',
                'ngay_duyet' => $pending ? null : '2023-01-10 10:00:00', 'ID_can_ho' => $a['ID_can_ho'], 'ID_nguoi_duyet' => $pending ? null : $LT]);
            if (!$pending) $people++;
        }
    }
    $OCC[$a['ID_can_ho']] = ['owner' => $owner, 'tenant' => $tenant, 'primary' => $tenant ?? $owner, 'people' => max(1, $people), 'apt' => $a];
    ResidentService::syncOccupancy((int)$a['ID_can_ho']);
}
$pdo->commit();
echo 'Căn hộ có người ở: ' . count($OCC) . ' · Nhân khẩu: ' . DB::val("SELECT COUNT(*) FROM nhan_khau") . "\n";

/* ---------- Thẻ ra vào & thẻ xe ---------- */
$pdo->beginTransaction();
$rf = 1; $fp = 1; $elv = 10000;
$plates = [];
$plate = function (string $type) use (&$plates) {
    do {
        $p = $type === 'o_to' ? sprintf('51%s-%03d.%02d', pick(['G', 'H', 'K', 'F', 'A']), mt_rand(100, 999), mt_rand(0, 99))
            : sprintf('59%s%d-%03d.%02d', pick(['X', 'V', 'T', 'P']), mt_rand(1, 9), mt_rand(100, 999), mt_rand(0, 99));
    } while (isset($plates[$p]));
    $plates[$p] = 1;
    return $p;
};
$brandsCar = [['Mazda CX-5', 'Trắng'], ['Toyota Vios', 'Bạc'], ['Honda City', 'Đen'], ['Kia Seltos', 'Đỏ'], ['VinFast VF 6', 'Xanh'], ['Hyundai Accent', 'Trắng']];
$brandsBike = [['Honda Vision', 'Trắng'], ['Yamaha Exciter', 'Xanh'], ['Honda Air Blade', 'Đen'], ['Honda SH Mode', 'Đỏ'], ['VinFast Evo200', 'Xám'], ['Yamaha Janus', 'Hồng']];
$pendingV = 0;
foreach ($OCC as $cid => $o) {
    $ma = $o['apt']['ma_can_ho'];
    $n = $ma === 'A-1001' ? [0, 2] : [chance(0.22) ? 1 : 0, mt_rand(0, 2)];
    if ($ma === 'A-1002') $n = [1, 1];
    foreach (['o_to' => $n[0], 'xe_may' => $n[1]] as $type => $cnt) {
        for ($i = 0; $i < $cnt; $i++) {
            [$b, $c] = pick($type === 'o_to' ? $brandsCar : $brandsBike);
            $pending = $pendingV < 10 && chance(0.012);
            ins('the_xe', ['bien_so_xe' => $ma === 'A-1001' ? ($i ? '59X3-270.34' : '59X2-213.21') : $plate($type), 'loai_xe' => $type, 'nhan_hieu' => $b, 'mau_xe' => $c,
                'anh_ca_vet_xe' => 'seed/ca_vet.jpg', 'trang_thai_duyet' => $pending ? 'cho_duyet' : 'da_duyet',
                'ngay_gui' => $pending ? date('Y-m-d H:i:s', strtotime("$TODAY -" . mt_rand(0, 3) . ' days 09:' . mt_rand(10, 59))) : '2023-02-01 09:00:00',
                'ngay_duyet' => $pending ? null : '2023-02-02 10:00:00', 'ID_nguoi_duyet' => $pending ? null : $LT, 'ID_can_ho' => $cid, 'ID_nhan_khau' => $o['primary']]);
            if ($pending) $pendingV++;
        }
    }
    $holder = $o['primary'];
    ins('the_ra_vao', ['ma_chip_rfid' => sprintf('ELV-%s%05d', $o['apt']['thap_nha'], $elv++), 'loai_the' => 'thang_may', 'ID_nhan_khau' => $holder,
        'trang_thai_the' => chance(0.01) ? 'bao_mat' : 'kich_hoat', 'ngay_cap' => '2023-01-05 09:00:00']);
    if ($n[0] + $n[1] > 0) ins('the_ra_vao', ['ma_chip_rfid' => sprintf('RFID-%s%05d', $o['apt']['thap_nha'], $rf++), 'loai_the' => 'rfid_xe', 'ID_nhan_khau' => $holder,
        'trang_thai_the' => chance(0.01) ? 'tam_khoa' : 'kich_hoat', 'ngay_cap' => '2023-02-03 09:00:00']);
    if (chance(0.6) || $ma === 'A-1001') ins('the_ra_vao', ['ma_id_van_tay' => sprintf('FP-%06d', $fp++ * 37), 'loai_the' => 'van_tay', 'ID_nhan_khau' => $holder, 'ngay_cap' => '2023-01-05 09:00:00']);
}
$pdo->commit();
echo 'Thẻ xe đã duyệt: ' . DB::val("SELECT COUNT(*) FROM the_xe WHERE trang_thai_duyet='da_duyet'") . ' · chờ duyệt: ' . $pendingV . "\n";

/* ---------- Chỉ số & hóa đơn 12 kỳ (11/2025 – 10/2026) ---------- */
$feeMap = FeeService::all();
$periods = [];
for ($i = 12; $i >= 0; $i--) $periods[] = date('Y-m', strtotime(substr($TODAY, 0, 7) . "-01 -" . ($i + 1) . ' month'));
// $periods: 2025-10 (kỳ gốc) ... 2026-10
$last = end($periods);
$SEQ = 1000;
$banks = ['VCB', 'BIDV', 'TCB', 'NCB', 'MB', 'ACB', 'VPB'];
$readings = [];
$nInv = 0;
foreach ($OCC as $cid => $o) {
    $a = $o['apt'];
    $baseD = (int)round($a['dien_tich'] * 2.3 * (0.75 + mt_rand(0, 60) / 100));
    $baseW = 4 * $o['people'] + mt_rand(0, 4);
    $d = mt_rand(300, 3000); $w = mt_rand(50, 400);
    foreach ($periods as $k => $ky) {
        $useD = max(20, (int)round($baseD * (0.85 + mt_rand(0, 30) / 100) * (in_array(substr($ky, 5), ['04', '05', '06']) ? 1.25 : 1)));
        $useW = max(3, (int)round($baseW * (0.85 + mt_rand(0, 30) / 100)));
        if ($a['ma_can_ho'] === 'A-1001' && $ky === $last) { $useD = 117; $useW = 9; }
        if ($a['thap_nha'] === 'D' && $ky === $last && in_array($a['ma_can_ho'], ['D-1005', 'D-1203'], true)) $useD = $baseD * 4;   // bất thường
        $readings[$cid][$ky] = [$d, $d + $useD, $w, $w + $useW];
        $d += $useD; $w += $useW;
    }
}
$pdo->beginTransaction();
foreach ($OCC as $cid => $o) {
    $a = $o['apt'];
    foreach ($periods as $k => $ky) {
        [$d0, $d1, $w0, $w1] = $readings[$cid][$ky];
        $issued = $k > 0 && !($ky === $last && $a['thap_nha'] === 'D');
        $approved = $issued && !($ky === $last && $a['thap_nha'] === 'C');
        ins('chi_so_dien_nuoc', ['ky_cuoc' => $ky, 'chi_so_dien_cu' => $d0, 'chi_so_dien_moi' => $d1, 'chi_so_nuoc_cu' => $w0, 'chi_so_nuoc_moi' => $w1,
            'da_khoa_so' => $approved ? 1 : 0, 'ngay_nhap' => date('Y-m-d 15:00:00', strtotime("$ky-01 +1 month +0 days")), 'ID_can_ho' => $cid, 'ID_nguoi_nhap' => $KT]);
        if (!$issued) continue;
        $issueDay = date('Y-m-d', strtotime("$ky-01 +1 month +1 day"));
        $hd = ins('hoa_don', ['ma_hoa_don' => 'HD-' . str_replace('-', '', $ky) . '-' . str_replace('-', '', $a['ma_can_ho']), 'ky_cuoc' => $ky,
            'ngay_lap' => "$issueDay 08:30:00", 'ID_can_ho' => $cid, 'ID_nguoi_lap' => $KT]);
        $lines = BillingService::computeLines($cid, ['chi_so_dien_cu' => $d0, 'chi_so_dien_moi' => $d1, 'chi_so_nuoc_cu' => $w0, 'chi_so_nuoc_moi' => $w1], $feeMap);
        $vals = []; $p = [];
        foreach ($lines as $l) { $vals[] = '(?,?,?,?,?,?)'; array_push($p, $l['loai_cuoc'], $l['dien_giai'], $l['so_luong'], $l['don_gia'], $l['thanh_tien'], $hd); }
        DB::q("INSERT INTO chi_tiet_hoa_don(loai_cuoc, dien_giai, so_luong, don_gia, thanh_tien, ID_hoa_don) VALUES " . implode(',', $vals), $p);
        $nInv++;
        if (!$approved) continue;
        $due = date('Y-m-d', strtotime("$issueDay +15 days"));
        DB::q("UPDATE hoa_don SET trang_thai_thanh_toan='chua_thanh_toan', ngay_phat_hanh=?, han_thanh_toan=?, ID_nguoi_duyet=? WHERE ID_hoa_don=?",
            ["$issueDay 16:00:00", $due, $KTT, $hd]);
        // Thanh toán
        $pPay = $ky === $last ? 0.3 : ($ky === $periods[count($periods) - 2] ? 0.88 : 0.97);
        if ($a['ma_can_ho'] === 'A-1001') $pPay = $ky === $last ? 0 : 1;
        if ($a['ma_can_ho'] === 'A-1002') $pPay = $ky === $last ? 0 : 1;
        if (!chance($pPay)) continue;
        $tong = (float)DB::val("SELECT tong_tien FROM hoa_don WHERE ID_hoa_don=?", [$hd]);
        $payTs = strtotime("$issueDay +" . mt_rand(0, 14) . ' days ' . mt_rand(7, 21) . ':' . sprintf('%02d', mt_rand(0, 59)));
        if ($payTs > strtotime(app_now())) $payTs = strtotime(app_now()) - mt_rand(600, 86400);
        $payAt = date('Y-m-d H:i:s', $payTs);
        $method = chance(0.75) ? 'vnpay' : (chance(0.8) ? 'tien_mat' : 'chuyen_khoan');
        if ($method === 'vnpay' && chance(0.04)) {   // một lần thanh toán thất bại trước đó
            $ref = 'VNP' . date('ymdHis', $payTs - 600) . ($SEQ++);
            ins('giao_dich_thanh_toan', ['ma_giao_dich_ref' => $ref, 'hinh_thuc' => 'vnpay', 'so_tien' => $tong, 'ma_ngan_hang' => pick($banks),
                'ma_phan_hoi' => '24', 'chuoi_bam_secure_hash' => hash('sha512', $ref), 'trang_thai' => 'that_bai', 'ngay_giao_dich' => date('Y-m-d H:i:s', $payTs - 600), 'ID_hoa_don' => $hd]);
        }
        if ($method === 'vnpay') {
            $ref = 'VNP' . date('ymdHis', $payTs) . ($SEQ++);
            ins('giao_dich_thanh_toan', ['ma_giao_dich_ref' => $ref, 'hinh_thuc' => 'vnpay', 'so_tien' => $tong, 'ma_ngan_hang' => pick($banks),
                'ma_gd_ngan_hang' => (string)mt_rand(14000000, 14999999), 'ma_phan_hoi' => '00', 'chuoi_bam_secure_hash' => hash('sha512', $ref),
                'trang_thai' => 'thanh_cong', 'ngay_giao_dich' => $payAt, 'ID_hoa_don' => $hd]);
        } else {
            ins('giao_dich_thanh_toan', ['ma_giao_dich_ref' => 'PT' . date('ymdHis', $payTs) . ($SEQ++), 'hinh_thuc' => $method, 'so_tien' => $tong,
                'trang_thai' => 'thanh_cong', 'so_chung_tu' => 'PT-' . date('Y', $payTs) . '-' . $SEQ, 'nguoi_nop' => DB::val("SELECT ho_ten FROM nhan_khau WHERE ID_nhan_khau=?", [$o['primary']]),
                'ngay_giao_dich' => $payAt, 'ID_hoa_don' => $hd, 'ID_nguoi_thuc_hien' => $KT]);
        }
        DB::q("UPDATE hoa_don SET trang_thai_thanh_toan='da_thanh_toan', ngay_thanh_toan=?, phuong_thuc_thanh_toan=? WHERE ID_hoa_don=?", [$payAt, $method, $hd]);
    }
    if ($nInv % 2000 < 13) { $pdo->commit(); $pdo->beginTransaction(); }
}
$pdo->commit();
// Hai giao dịch ngoại lệ phục vụ đối soát
foreach (DB::all("SELECT ID_hoa_don, tong_tien FROM hoa_don WHERE trang_thai_thanh_toan='chua_thanh_toan' AND ky_cuoc=? ORDER BY ID_hoa_don LIMIT 2", [$last]) as $i => $h) {
    ins('giao_dich_thanh_toan', ['ma_giao_dich_ref' => 'VNP' . date('ymd') . '0900' . ($i + 10) . '7', 'hinh_thuc' => 'vnpay', 'so_tien' => $h['tong_tien'], 'ma_ngan_hang' => 'NCB',
        'trang_thai' => 'ngoai_le', 'ghi_chu' => $i ? 'Sai số tiền IPN' : 'Sai chữ ký HMAC SHA512', 'ngay_giao_dich' => date('Y-m-d 09:0' . $i . ':00', strtotime($TODAY . ' -1 day')), 'ID_hoa_don' => $h['ID_hoa_don']]);
}
echo "Hóa đơn: $nInv · Giao dịch: " . DB::val("SELECT COUNT(*) FROM giao_dich_thanh_toan") . "\n";

/* ---------- Tiện ích & lịch đặt ---------- */
$F = [];
foreach ([['BBQ', 'Khu BBQ ngoài trời', 40, '08:00', '22:00', 1, 2], ['POOL', 'Hồ bơi', 60, '06:00', '22:00', 0, null],
          ['SPORT', 'Sân thể thao', 16, '06:00', '22:00', 1, 2], ['HALL', 'Phòng sinh hoạt cộng đồng', 50, '08:00', '22:00', 1, 2]] as [$c, $n, $cap, $o, $cl, $dq, $hm]) {
    $F[$c] = ins('dich_vu_tien_ich', ['ma_tien_ich' => $c, 'ten_tien_ich' => $n, 'suc_chua_toi_da' => $cap, 'gio_mo_cua' => $o, 'gio_dong_cua' => $cl,
        'doc_quyen' => $dq, 'han_muc_tuan' => $hm]);
}
$facil = DB::all("SELECT * FROM dich_vu_tien_ich");
$taken = [];
$weekly = [];
$occIds = array_keys($OCC);
$book = function (int $cid, string $code, string $date, string $start, int $ppl, ?string $status = null) use (&$taken, &$weekly, $OCC, $F, $facil, $TODAY) {
    $f = null; foreach ($facil as $x) if ($x['ma_tien_ich'] === $code) $f = $x;
    $key = "$code|$date|$start";
    $wk = $cid . '|' . $code . '|' . date('oW', strtotime($date));
    if ($f['doc_quyen'] && isset($taken[$key])) return false;
    if ($f['han_muc_tuan'] && ($weekly[$wk] ?? 0) >= $f['han_muc_tuan']) return false;
    if (!$f['doc_quyen'] && ($taken[$key] ?? 0) + $ppl > $f['suc_chua_toi_da']) return false;
    if ($f['doc_quyen'] && BillingService::overdue30($cid)) return false;
    $taken[$key] = $f['doc_quyen'] ? 1 : ($taken[$key] ?? 0) + $ppl;
    $weekly[$wk] = ($weekly[$wk] ?? 0) + 1;
    $o = $OCC[$cid];
    $end = date('H:i', strtotime($start) + 7200);
    $status = $status ?? ($date < $TODAY ? (chance(0.9) ? 'da_su_dung' : 'huy_muon') : 'da_xac_nhan');
    $nk = DB::one("SELECT ID_tai_khoan FROM nhan_khau WHERE ID_nhan_khau=?", [$o['primary']]);
    ins('dat_tien_ich', ['ma_ve' => sprintf('MOONS-%s-%s-%s-%s-%04X', $code, str_replace('-', '', $o['apt']['ma_can_ho']), str_replace('-', '', $date), substr($start, 0, 2), mt_rand(0, 65535)),
        'ngay_dat' => $date, 'khung_gio_bat_dau' => $start, 'khung_gio_ket_thuc' => $end, 'so_nguoi' => $ppl, 'trang_thai_dat' => $status,
        'ngay_tao' => date('Y-m-d H:i:s', strtotime("$date -" . mt_rand(1, 6) . ' days 20:00')), 'ngay_huy' => $status === 'huy_muon' ? "$date 07:00:00" : null,
        'ID_tien_ich' => $F[$code], 'ID_nhan_khau' => $o['primary'], 'ID_can_ho' => $cid, 'ID_nguoi_tao' => $nk['ID_tai_khoan']]);
    return true;
};
ins('dat_tien_ich', ['ma_ve' => 'BT-' . date('ymd') . '001', 'loai' => 'bao_tri', 'ngay_dat' => date('Y-m-d', strtotime("$TODAY +1 day")), 'khung_gio_bat_dau' => '08:00',
    'khung_gio_ket_thuc' => '22:00', 'trang_thai_dat' => 'da_xac_nhan', 'ID_tien_ich' => $F['HALL'], 'ID_nguoi_tao' => $KTT]);
foreach (['08:00','10:00','12:00','14:00','16:00','18:00','20:00'] as $h) $taken['HALL|' . date('Y-m-d', strtotime("$TODAY +1 day")) . "|$h"] = 1;
$cidA1001 = (int)DB::val("SELECT ID_can_ho FROM can_ho WHERE ma_can_ho='A-1001'");
$book($cidA1001, 'BBQ', date('Y-m-d', strtotime("$TODAY +2 days")), '12:00', 7);
$book($cidA1001, 'POOL', date('Y-m-d', strtotime("$TODAY +4 days")), '16:00', 3);
for ($i = 0; $i < 260; $i++) {
    $date = date('Y-m-d', strtotime("$TODAY " . (mt_rand(-14, 12) >= 0 ? '+' : '') . mt_rand(-14, 12) . ' days'));
    $code = pick(['BBQ', 'BBQ', 'POOL', 'POOL', 'SPORT', 'SPORT', 'HALL']);
    $start = ['BBQ' => pick(['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00']), 'HALL' => pick(['08:00', '10:00', '14:00', '18:00']),
        'POOL' => pick(['06:00', '08:00', '16:00', '18:00']), 'SPORT' => pick(['06:00', '16:00', '18:00', '20:00'])][$code];
    if ($date === $TODAY && $start <= date('H:i')) continue;
    $book(pick($occIds), $code, $date, $start, $code === 'POOL' ? mt_rand(1, 5) : mt_rand(4, 15));
}
echo 'Lịch đặt tiện ích: ' . DB::val("SELECT COUNT(*) FROM dat_tien_ich") . "\n";

/* ---------- Phiếu sự cố ---------- */
$titles = ['Rò rỉ nước' => 'can_ho', 'Đèn hành lang không sáng' => 'khu_vuc_chung', 'Thẻ từ không hoạt động' => 'can_ho', 'Cửa ra vào bị kẹt' => 'can_ho',
    'Điều hòa phát tiếng ồn' => 'can_ho', 'Tắc nghẽn đường ống' => 'can_ho', 'Thang máy dừng không đúng tầng' => 'khu_vuc_chung', 'Ổ cắm bị chập' => 'can_ho',
    'Vòi sen yếu nước' => 'can_ho', 'Camera hầm xe không hoạt động' => 'khu_vuc_chung'];
$states = ['cho_tiep_nhan' => 6, 'da_phan_cong' => 8, 'dang_xu_ly' => 10, 'cho_linh_kien' => 5, 'cho_nghiem_thu' => 7, 'hoan_tat' => 55, 'tu_choi' => 3, 'da_huy' => 2];
$tid = 0;
$mkTicket = function (int $cid, string $title, string $scope, string $state, int $agoMin, string $prio, ?int $tech = null) use (&$tid, $OCC, $TECHS, $DPV) {
    $o = $OCC[$cid];
    $created = date('Y-m-d H:i:s', strtotime(app_now()) - $agoMin * 60);
    $assigned = in_array($state, ['cho_tiep_nhan', 'tu_choi', 'da_huy']) ? null : date('Y-m-d H:i:s', strtotime($created) + ($prio === 'khan_cap' ? mt_rand(4, 14) : mt_rand(20, 240)) * 60);
    $done = $state === 'hoan_tat' ? date('Y-m-d H:i:s', min(strtotime(app_now()) - 60, strtotime($created) + mt_rand(4, 72) * 3600)) : null;
    $id = ins('phieu_su_co', ['ma_phieu' => 'TK-TMP' . (++$tid), 'tieu_de' => $title, 'mo_ta_chi_tiet' => "Cư dân phản ánh: $title. Đề nghị Ban Quản lý kiểm tra và xử lý.",
        'pham_vi' => $scope, 'vi_tri_su_co' => $scope === 'khu_vuc_chung' ? 'Tầng ' . $o['apt']['so_tang'] . ' – Tháp ' . $o['apt']['thap_nha'] : null,
        'muc_do_uu_tien' => $prio, 'trang_thai_kanban' => $state, 'anh_hien_truong' => 'seed/hien_truong.jpg',
        'anh_nghiem_thu' => in_array($state, ['cho_nghiem_thu', 'hoan_tat']) ? 'seed/nghiem_thu.jpg' : null,
        'vat_tu_tieu_hao' => in_array($state, ['cho_nghiem_thu', 'hoan_tat']) ? pick(['01 gioăng cao su', '02 bóng LED 9W', '01 ổ cắm âm tường', 'Không phát sinh vật tư']) : null,
        'ghi_chu_ky_thuat' => $state === 'cho_linh_kien' ? pick(['Chờ tụ điện điều hòa', 'Chờ bản lề cửa', 'Chờ van xả']) : (in_array($state, ['cho_nghiem_thu', 'hoan_tat']) ? 'Đã kiểm tra và khắc phục, vận hành bình thường.' : null),
        'ly_do_tu_choi' => $state === 'tu_choi' ? 'Phiếu trùng lặp với phiếu đã tiếp nhận' : null,
        'diem_danh_gia' => $state === 'hoan_tat' ? pick([5, 5, 5, 4, 4, 3]) : null, 'ngay_tao' => $created, 'ngay_phan_cong' => $assigned, 'ngay_hoan_thanh' => $done,
        'ID_can_ho' => $cid, 'ID_nguoi_gui' => $o['primary'], 'ID_ky_thuat_vien' => $assigned ? ($tech ?? pick($TECHS)) : null, 'ID_nguoi_phan_cong' => $assigned ? $DPV : null]);
    DB::q("UPDATE phieu_su_co SET ma_phieu=? WHERE ID_phieu=?", ['TK-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT), $id]);
};
foreach ($states as $st => $n) for ($i = 0; $i < $n; $i++) {
    $t = array_rand($titles);
    $mkTicket(pick($occIds), $t, $titles[$t], $st, $st === 'hoan_tat' ? mt_rand(2000, 80000) : mt_rand(30, 9000), chance(0.2) ? 'khan_cap' : 'binh_thuong');
}
$mkTicket($cidA1001, 'Bóng đèn ban công không sáng', 'can_ho', 'hoan_tat', 30000, 'binh_thuong', $S['namhv']);
$mkTicket($cidA1001, 'Rò rỉ ống nước nhà vệ sinh', 'can_ho', 'dang_xu_ly', 1500, 'khan_cap', $S['namhv']);
$mkTicket($cidA1001, 'Chuông cửa không kêu', 'can_ho', 'cho_nghiem_thu', 2900, 'binh_thuong', $S['huongpt']);
$mkTicket(pick($occIds), 'Rò rỉ điện tủ điện tầng', 'khu_vuc_chung', 'cho_tiep_nhan', 42, 'khan_cap');
echo 'Phiếu sự cố: ' . DB::val("SELECT COUNT(*) FROM phieu_su_co") . "\n";

/* ---------- Tài liệu ngữ cảnh AI ---------- */
$docs = require __DIR__ . '/ai_docs.php';
foreach ($docs as $i => [$t, $l, $c, $by]) ins('ngu_canh_ai', ['tieu_de_tai_lieu' => $t, 'loai_tai_lieu' => $l, 'noi_dung_ngu_canh' => $c,
    'trang_thai' => 'dang_su_dung', 'ngay_cap_nhat' => sprintf('2026-09-%02d 08:00:00', $i + 1), 'ID_nguoi_cap_nhat' => $S[$by]]);

AuditService::log('khoi_tao_du_lieu', 'he_thong', null, 'Seed dữ liệu mô phỏng', $ADM);
printf("Hoàn tất trong %.1f giây.\n", microtime(true) - $T0);
