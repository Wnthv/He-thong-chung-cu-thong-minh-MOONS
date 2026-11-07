<?php
/**
 * BỘ KIỂM THỬ TỰ ĐỘNG – chạy trên CSDL riêng (mặc định moons_test, khôi phục từ bản seed)
 *   MOONS_DB=moons_test php tests/run_tests.php [--http=http://127.0.0.1:8080/index.php]
 * Nhóm DB : quy tắc do CSDL tự bảo đảm (CHECK/UNIQUE/FK/TRIGGER) – thao tác SQL trực tiếp, bỏ qua Service
 * Nhóm SV : quy tắc do lớp Service bảo đảm
 * Nhóm PQ : phân quyền & bảo mật qua HTTP (yêu cầu máy chủ web đang chạy với CSDL seed)
 */
require __DIR__ . '/../app/bootstrap.php';
$_SESSION = [];
$results = [];
$httpBase = null;
foreach ($argv as $a) if (str_starts_with($a, '--http=')) $httpBase = substr($a, 7);

function asUser(string $username): array
{
    $u = DB::one("SELECT * FROM tai_khoan WHERE ten_dang_nhap=?", [$username]);
    $_SESSION['user'] = $u;
    return $u;
}
function resident(string $ma, string $type = 'chu_ho'): array
{
    $me = DB::one("SELECT nk.*, ch.ma_can_ho, ch.dien_tich, ch.thap_nha, ch.trang_thai_o, tk.vai_tro, tk.ID_tai_khoan, tk.ten_dang_nhap
                     FROM nhan_khau nk JOIN can_ho ch ON ch.ID_can_ho=nk.ID_can_ho JOIN tai_khoan tk ON tk.ID_tai_khoan=nk.ID_tai_khoan
                    WHERE ch.ma_can_ho=? AND nk.loai_cu_dan=? AND nk.trang_thai_duyet='da_duyet'", [$ma, $type]);
    $_SESSION['user'] = DB::one("SELECT * FROM tai_khoan WHERE ID_tai_khoan=?", [$me['ID_tai_khoan']]);
    return $me;
}
/** Căn hộ chủ hộ đang ở, không nợ quá hạn, chưa có lịch đặt – dùng cho kiểm thử tiện ích */
function cleanApt(int $skip = 0): array
{
    $ma = DB::val("SELECT ch.ma_can_ho FROM can_ho ch WHERE ch.trang_thai_o='chu_ho_o' AND ch.loai_can='can_ho'
        AND NOT EXISTS (SELECT 1 FROM dat_tien_ich d WHERE d.ID_can_ho=ch.ID_can_ho)
        AND NOT EXISTS (SELECT 1 FROM hoa_don h WHERE h.ID_can_ho=ch.ID_can_ho AND h.trang_thai_thanh_toan='chua_thanh_toan' AND h.han_thanh_toan < CURDATE() - INTERVAL 30 DAY)
        ORDER BY ch.ID_can_ho LIMIT 1 OFFSET $skip");
    return resident($ma);
}
/** Ghi nhận 1 ca kiểm thử. $fn trả về chuỗi "kết quả thực tế"; ném lỗi => ghi nhận thông điệp lỗi */
function tc(string $id, string $rule, string $layer, string $input, string $expected, callable $fn, callable $pass): void
{
    global $results;
    try { $actual = $fn(); } catch (Throwable $e) { $actual = 'LỖI: ' . ($e instanceof PDOException ? db_error_message($e) : $e->getMessage()); }
    $ok = (bool)$pass($actual);
    $results[] = compact('id', 'rule', 'layer', 'input', 'expected', 'actual') + ['pass' => $ok];
    printf("%-6s %-4s %s\n       → %s\n", $id, $ok ? 'ĐẠT' : 'LỖI', $rule, mb_substr(str_replace("\n", ' ', $actual), 0, 150));
}
$has = fn(string $needle) => fn($a) => str_contains((string)$a, $needle);
$sql = function (string $q, array $p = []) { DB::q($q, $p); return 'Câu lệnh thực thi thành công'; };
$signIpn = function (array $gd, string $code = '00', ?int $amount = null) {
    $q = ['vnp_Amount' => $amount ?? (int)round($gd['so_tien'] * 100), 'vnp_BankCode' => 'NCB', 'vnp_OrderInfo' => 'Thanh toan',
        'vnp_PayDate' => date('YmdHis'), 'vnp_ResponseCode' => $code, 'vnp_TmnCode' => 'MOONSDEMO', 'vnp_TransactionNo' => '14123456',
        'vnp_TransactionStatus' => $code === '00' ? '00' : '02', 'vnp_TxnRef' => $gd['ma_giao_dich_ref']];
    $q['vnp_SecureHash'] = PaymentService::sign($q);
    return $q;
};
$unpaid = fn(string $ma) => DB::one("SELECT h.* FROM hoa_don h JOIN can_ho ch ON ch.ID_can_ho=h.ID_can_ho WHERE ch.ma_can_ho=? AND h.trang_thai_thanh_toan='chua_thanh_toan' ORDER BY ky_cuoc DESC LIMIT 1", [$ma]);
$anyUnpaid = fn(int $skip = 0) => DB::one("SELECT h.*, ch.ma_can_ho FROM hoa_don h JOIN can_ho ch ON ch.ID_can_ho=h.ID_can_ho
     WHERE h.trang_thai_thanh_toan='chua_thanh_toan' AND ch.trang_thai_o='chu_ho_o' AND h.ky_cuoc='2026-10' ORDER BY h.ID_hoa_don LIMIT 1 OFFSET $skip");
$D3 = date('Y-m-d', strtotime(today() . ' +3 days'));

echo "=== CSDL kiểm thử: " . DB::val("SELECT DATABASE()") . " · ngày " . today() . " ===\n";

/* ================= NHÓM DB: CSDL tự bảo đảm ================= */
$h = $anyUnpaid();
tc('DB-01', 'tong_tien luôn bằng tổng thành tiền chi tiết', 'TRIGGER trg_hd_bu', "UPDATE hoa_don SET tong_tien=1 (HĐ {$h['ma_hoa_don']})",
    'Giá trị bị trigger tính lại, không đổi', function () use ($h) {
        DB::q("UPDATE hoa_don SET tong_tien=1 WHERE ID_hoa_don=?", [$h['ID_hoa_don']]);
        $t = DB::val("SELECT tong_tien FROM hoa_don WHERE ID_hoa_don=?", [$h['ID_hoa_don']]);
        return "tong_tien sau UPDATE = " . money($t) . " (ban đầu " . money($h['tong_tien']) . ')';
    }, fn($a) => str_contains($a, money($h['tong_tien']) . ' (ban'));
tc('DB-02', 'Không sửa chi tiết của hóa đơn đã phát hành', 'TRIGGER trg_cthd_bi/bu/bd', 'INSERT/UPDATE/DELETE chi_tiet_hoa_don của HĐ chưa thanh toán',
    'Bị chặn: HD_DA_PHAT_HANH', fn() => $sql("UPDATE chi_tiet_hoa_don SET so_luong=0, thanh_tien=0 WHERE ID_hoa_don=? LIMIT 1", [$h['ID_hoa_don']]), $has('đã phát hành'));
tc('DB-03', 'Không gạch nợ trực tiếp khi chưa có giao dịch thành công', 'TRIGGER trg_hd_bu', "UPDATE hoa_don SET trang_thai='da_thanh_toan'",
    'Bị chặn: HD_CHUA_CO_GIAO_DICH', fn() => $sql("UPDATE hoa_don SET trang_thai_thanh_toan='da_thanh_toan', ngay_thanh_toan=NOW(), phuong_thuc_thanh_toan='tien_mat' WHERE ID_hoa_don=?", [$h['ID_hoa_don']]), $has('chưa có giao dịch'));
tc('DB-04', 'Giao dịch thành công phải khớp số tiền hóa đơn', 'TRIGGER trg_gd_bi', "Ghi GD 'thành công' 1.000 đ cho HĐ {$h['ma_hoa_don']} (" . money($h['tong_tien']) . ')',
    'Bị chặn: GD_KHONG_HOP_LE', fn() => $sql("INSERT INTO giao_dich_thanh_toan(ma_giao_dich_ref,hinh_thuc,so_tien,trang_thai,ID_hoa_don) VALUES ('TEST-DB04','vnpay',1000,'thanh_cong',?)", [$h['ID_hoa_don']]),
    $has('phải khớp số tiền'));
$paid = DB::one("SELECT * FROM hoa_don WHERE trang_thai_thanh_toan='da_thanh_toan' LIMIT 1");
tc('DB-05', 'Mỗi hóa đơn tối đa 1 giao dịch thành công', 'TRIGGER trg_gd_bi + UNIQUE uq_gd_hd_tc', "Thêm GD thành công thứ 2 cho HĐ {$paid['ma_hoa_don']}",
    'Bị chặn (trigger chặn trước, UNIQUE là lớp bảo vệ thứ hai)', fn() => $sql("INSERT INTO giao_dich_thanh_toan(ma_giao_dich_ref,hinh_thuc,so_tien,trang_thai,ID_hoa_don) VALUES ('TEST-DB05','vnpay',?, 'thanh_cong',?)", [$paid['tong_tien'], $paid['ID_hoa_don']]),
    fn($a) => str_contains($a, 'đã được thanh toán bằng hình thức khác') || str_contains($a, 'đã được gạch nợ'));
tc('DB-06', 'Mỗi căn hộ 1 hóa đơn còn hiệu lực/kỳ', 'UNIQUE uq_hd_ky (cột sinh)', "Lập thêm HĐ kỳ {$h['ky_cuoc']} cho cùng căn hộ",
    'Bị chặn: trùng khóa', function () use ($h, $sql) {
        DB::q("INSERT INTO hoa_don(ma_hoa_don,ky_cuoc,ID_can_ho,ID_nguoi_lap) VALUES ('TEST-DB06',?,?,1)", [$h['ky_cuoc'], $h['ID_can_ho']]);
        return $sql("UPDATE hoa_don SET trang_thai_thanh_toan='chua_thanh_toan' WHERE ma_hoa_don='TEST-DB06'");
    }, fn($a) => str_contains($a, 'đã có hóa đơn') || str_contains($a, 'không hợp lệ'));
DB::q("UPDATE hoa_don SET trang_thai_thanh_toan='da_huy', ly_do_huy='Dọn dữ liệu kiểm thử DB-06' WHERE ma_hoa_don='TEST-DB06'");
tc('DB-07', 'Hóa đơn chỉ được hủy, không được xóa', 'TRIGGER trg_hd_bd + FK RESTRICT', "DELETE FROM hoa_don (HĐ {$paid['ma_hoa_don']})",
    'Bị chặn', fn() => $sql("DELETE FROM hoa_don WHERE ID_hoa_don=?", [$paid['ID_hoa_don']]), fn($a) => str_starts_with($a, 'LỖI'));
tc('DB-08', 'Không xóa căn hộ còn dữ liệu liên quan (truy vết)', 'FK ON DELETE RESTRICT', 'DELETE FROM can_ho WHERE ma_can_ho=\'A-1001\'',
    'Bị chặn bởi khóa ngoại', fn() => $sql("DELETE FROM can_ho WHERE ma_can_ho='A-1001'"), $has('foreign key'));
$lockedCs = DB::one("SELECT * FROM chi_so_dien_nuoc WHERE da_khoa_so=1 LIMIT 1");
tc('DB-09', 'Chỉ số đã khóa sổ không được sửa', 'TRIGGER trg_cs_bu', 'UPDATE chi_so_dien_moi của dòng đã khóa sổ', 'Bị chặn: CS_KHOA_SO',
    fn() => $sql("UPDATE chi_so_dien_nuoc SET chi_so_dien_moi=chi_so_dien_moi+5 WHERE ID_chi_so=?", [$lockedCs['ID_chi_so']]), $has('khóa sổ'));
tc('DB-10', 'Chỉ số mới ≥ chỉ số cũ', 'CHECK ck_cs_dien', 'INSERT chỉ số điện cũ 500, mới 400', 'Bị chặn',
    fn() => $sql("INSERT INTO chi_so_dien_nuoc(ky_cuoc,chi_so_dien_cu,chi_so_dien_moi,chi_so_nuoc_cu,chi_so_nuoc_moi,ID_can_ho,ID_nguoi_nhap) VALUES ('2030-01',500,400,1,2,1,1)"), $has('Chỉ số điện mới'));
tc('DB-11', 'Thành tiền = số lượng × đơn giá', 'CHECK ck_cthd_tt', 'INSERT dòng 10 × 1.000 = 99.999', 'Bị chặn',
    fn() => $sql("INSERT INTO chi_tiet_hoa_don(loai_cuoc,dien_giai,so_luong,don_gia,thanh_tien,ID_hoa_don) VALUES ('nuoc','x',10,1000,99999,?)", [DB::val("SELECT ID_hoa_don FROM hoa_don WHERE trang_thai_thanh_toan='cho_duyet' LIMIT 1")]),
    $has('ck_cthd_tt'));
$veh = DB::one("SELECT * FROM the_xe WHERE trang_thai_duyet='da_duyet' LIMIT 1");
tc('DB-12', 'Biển số không trùng giữa các hồ sơ còn hiệu lực', 'UNIQUE uq_tx_bien_so (cột sinh)', "Đăng ký lại biển {$veh['bien_so_xe']} ở căn khác",
    'Bị chặn', fn() => $sql("INSERT INTO the_xe(bien_so_xe,loai_xe,nhan_hieu,mau_xe,anh_ca_vet_xe,ID_can_ho,ID_nhan_khau) VALUES (?, ?, 'X','Y','seed/ca_vet.jpg',2,2)", [$veh['bien_so_xe'], $veh['loai_xe']]), $has('đã được đăng ký'));
tc('DB-13', 'Biển số của hồ sơ đã hủy được đăng ký lại', 'UNIQUE trên cột sinh (NULL khi đã hủy)', 'Hủy hồ sơ rồi đăng ký lại cùng biển số',
    'Thành công', function () use ($veh, $sql) {
        DB::q("UPDATE the_xe SET trang_thai_duyet='da_huy', ly_do='Kiểm thử' WHERE ID_the_xe=?", [$veh['ID_the_xe']]);
        return $sql("INSERT INTO the_xe(bien_so_xe,loai_xe,nhan_hieu,mau_xe,anh_ca_vet_xe,ID_can_ho,ID_nhan_khau) VALUES (?, ?, 'X','Y','seed/ca_vet.jpg',?,?)", [$veh['bien_so_xe'], $veh['loai_xe'], $veh['ID_can_ho'], $veh['ID_nhan_khau']]);
    }, $has('thành công'));
tc('DB-14', 'Biển số đúng định dạng Việt Nam', 'CHECK ck_tx_bien_so', "Biển số 'ABC-123'", 'Bị chặn',
    fn() => $sql("INSERT INTO the_xe(bien_so_xe,loai_xe,nhan_hieu,mau_xe,anh_ca_vet_xe,ID_can_ho,ID_nhan_khau) VALUES ('ABC-123','o_to','X','Y','a',1,1)"), $has('định dạng'));
$tk = DB::one("SELECT * FROM phieu_su_co WHERE trang_thai_kanban='dang_xu_ly' LIMIT 1");
tc('DB-15', 'Phải có ảnh nghiệm thu trước khi chờ nghiệm thu/hoàn tất', 'CHECK ck_psc_anh', "UPDATE phiếu {$tk['ma_phieu']} sang cho_nghiem_thu, không ảnh",
    'Bị chặn', fn() => $sql("UPDATE phieu_su_co SET trang_thai_kanban='cho_nghiem_thu', anh_nghiem_thu=NULL WHERE ID_phieu=?", [$tk['ID_phieu']]), $has('ảnh nghiệm thu'));
tc('DB-16', 'Khách thuê phải có hợp đồng hợp lệ', 'CHECK ck_nk_hop_dong', 'INSERT khách thuê ngày kết thúc < ngày bắt đầu', 'Bị chặn',
    fn() => $sql("INSERT INTO nhan_khau(ho_ten,loai_cu_dan,so_hop_dong,ngay_bat_dau_hop_dong,ngay_ket_thuc_hop_dong,ID_can_ho) VALUES ('Test','khach_thue','HD1','2026-10-01','2026-01-01',1)"), $has('hợp đồng'));
$gdOk = DB::one("SELECT * FROM giao_dich_thanh_toan WHERE trang_thai='thanh_cong' LIMIT 1");
tc('DB-17', 'Giao dịch đã kết thúc là bất biến', 'TRIGGER trg_gd_bu/bd', "UPDATE so_tien của GD {$gdOk['ma_giao_dich_ref']}", 'Bị chặn',
    fn() => $sql("UPDATE giao_dich_thanh_toan SET so_tien=1 WHERE ID_giao_dich=?", [$gdOk['ID_giao_dich']]), $has('kết thúc'));
tc('DB-18', 'Nhật ký thao tác chỉ ghi thêm', 'TRIGGER trg_nk_bu/bd', 'DELETE FROM nhat_ky_thao_tac', 'Bị chặn',
    fn() => $sql("DELETE FROM nhat_ky_thao_tac LIMIT 1"), $has('NK_CHI_GHI_THEM'));
tc('DB-19', 'Chỉ chuyển trạng thái hóa đơn hợp lệ', 'TRIGGER trg_hd_bu', "UPDATE HĐ đã thanh toán về 'chua_thanh_toan'", 'Bị chặn',
    fn() => $sql("UPDATE hoa_don SET trang_thai_thanh_toan='chua_thanh_toan' WHERE ID_hoa_don=?", [$paid['ID_hoa_don']]), $has('đã thanh toán'));

/* ================= NHÓM SV: lớp Service bảo đảm ================= */
tc('SV-01', 'Tính tiền điện 6 bậc (mỗi bậc 1 dòng)', 'FeeService', '117 kWh', '50×1.984 + 50×2.050 + 17×2.380 = 242.160 đ', function () {
    $l = FeeService::electricTiers(117);
    return implode(' + ', array_map(fn($x) => num($x['so_luong']) . '×' . num($x['don_gia']), $l)) . ' = ' . money(array_sum(array_column($l, 'thanh_tien')));
}, $has('242.160 đ'));
tc('SV-02', 'Lưu chỉ số: chỉ số mới nhỏ hơn cũ bị từ chối theo từng dòng', 'BillingService', 'Kế toán nhập điện mới = 1 cho 1 căn tháp D kỳ 2026-10',
    'Báo lỗi dòng, các dòng khác vẫn lưu', function () {
        asUser('toannk');
        $id = DB::val("SELECT c.ID_can_ho FROM chi_so_dien_nuoc c JOIN can_ho ch USING(ID_can_ho) WHERE ch.thap_nha='D' AND c.ky_cuoc='2026-10' AND da_khoa_so=0 LIMIT 1");
        $r = BillingService::saveReadings('2026-10', [$id => ['dien' => '1', 'nuoc' => '1']]);
        return "saved={$r['saved']}; lỗi: " . implode('; ', $r['errors']);
    }, $has('nhỏ hơn chỉ số cũ'));
tc('SV-03', 'Tiêu thụ > 3 lần TB 3 kỳ phải có ghi chú xác minh', 'BillingService', 'Lập HĐ tháp D kỳ 2026-10 (D-1005, D-1203 bất thường, chưa ghi chú)',
    'Bỏ qua 2 căn bất thường, lập HĐ Chờ duyệt cho các căn còn lại', function () {
        asUser('toannk');
        $r = BillingService::issueBatch('D', '2026-10');
        return "Lập {$r['created']} HĐ; bỏ qua: " . implode(' | ', $r['skipped']);
    }, fn($a) => str_contains($a, 'D-1005') && str_contains($a, 'D-1203') && preg_match('/Lập [1-9]\d* HĐ/', $a));
tc('SV-04', 'Ghi chú xác minh hợp lệ thì được lập hóa đơn', 'BillingService', 'Nhập ghi chú cho D-1005 rồi lập lại', 'Lập thêm 1 HĐ', function () {
    asUser('toannk');
    $c = DB::one("SELECT c.* FROM chi_so_dien_nuoc c JOIN can_ho ch USING(ID_can_ho) WHERE ch.ma_can_ho='D-1005' AND c.ky_cuoc='2026-10'");
    BillingService::saveReadings('2026-10', [$c['ID_can_ho'] => ['dien' => (string)$c['chi_so_dien_moi'], 'nuoc' => (string)$c['chi_so_nuoc_moi'], 'ghi_chu' => 'Đã kiểm tra công tơ, căn hộ lắp thêm điều hòa']]);
    return 'Lập ' . BillingService::issueBatch('D', '2026-10')['created'] . ' HĐ';
}, $has('Lập 1 HĐ'));
$cthd = DB::one("SELECT h.ID_hoa_don, h.tong_tien, SUM(c.thanh_tien) s, COUNT(*) n FROM hoa_don h JOIN chi_tiet_hoa_don c USING(ID_hoa_don) JOIN can_ho ch ON ch.ID_can_ho=h.ID_can_ho WHERE ch.ma_can_ho='D-1005' AND h.ky_cuoc='2026-10' GROUP BY h.ID_hoa_don");
tc('SV-05', 'Hóa đơn mới lập ở trạng thái Chờ duyệt, tổng = Σ chi tiết', 'BillingService + TRIGGER', 'Kiểm tra HĐ D-1005 vừa lập', 'Chờ duyệt, tong_tien = tổng dòng',
    fn() => DB::val("SELECT trang_thai_thanh_toan FROM hoa_don WHERE ID_hoa_don=?", [$cthd['ID_hoa_don']]) . "; tong_tien=" . money($cthd['tong_tien']) . "; Σ{$cthd['n']} dòng=" . money($cthd['s']),
    fn($a) => str_starts_with($a, 'cho_duyet') && money($cthd['tong_tien']) === money($cthd['s']));
tc('SV-06', 'Kế toán không được phê duyệt phát hành', 'BillingService (RBAC)', 'Kế toán gọi approveBatch(D, 2026-10)', 'Từ chối',
    function () { asUser('toannk'); return BillingService::approveBatch('D', '2026-10') . ' HĐ'; }, $has('Chỉ Kế toán trưởng'));
tc('SV-07', 'Kế toán trưởng phê duyệt phát hành và khóa sổ chỉ số', 'BillingService', 'KTT phê duyệt lô tháp D kỳ 2026-10', 'HĐ chuyển Chưa thanh toán, chỉ số khóa sổ',
    function () {
        asUser('hapt');
        $n = BillingService::approveBatch('D', '2026-10');
        $open = DB::val("SELECT COUNT(*) FROM hoa_don h JOIN chi_so_dien_nuoc c ON c.ID_can_ho=h.ID_can_ho AND c.ky_cuoc=h.ky_cuoc JOIN can_ho ch ON ch.ID_can_ho=h.ID_can_ho
                         WHERE ch.thap_nha='D' AND h.ky_cuoc='2026-10' AND h.trang_thai_thanh_toan='chua_thanh_toan' AND c.da_khoa_so=0");
        return "Phát hành $n HĐ; chỉ số chưa khóa sổ: $open";
    }, fn($a) => preg_match('/Phát hành [1-9]\d* HĐ; chỉ số chưa khóa sổ: 0/', $a));
tc('SV-08', 'Phân tách nhiệm vụ: KTT không duyệt HĐ do chính mình lập', 'BillingService', 'Lập HĐ bằng tài khoản KTT rồi tự duyệt', 'Từ chối', function () {
    $c = DB::one("SELECT c.ID_can_ho FROM chi_so_dien_nuoc c JOIN can_ho ch USING(ID_can_ho) WHERE ch.ma_can_ho='D-1203' AND c.ky_cuoc='2026-10'");
    asUser('hapt');
    BillingService::saveReadings('2026-10', [$c['ID_can_ho'] => ['dien' => (string)DB::val("SELECT chi_so_dien_moi FROM chi_so_dien_nuoc WHERE ID_can_ho=? AND ky_cuoc='2026-10'", [$c['ID_can_ho']]),
        'nuoc' => (string)DB::val("SELECT chi_so_nuoc_moi FROM chi_so_dien_nuoc WHERE ID_can_ho=? AND ky_cuoc='2026-10'", [$c['ID_can_ho']]), 'ghi_chu' => 'Kiểm tra lại công tơ tại hiện trường']]);
    BillingService::issueBatch('D', '2026-10');
    return BillingService::approveBatch('D', '2026-10') . ' HĐ';
}, $has('do chính bạn lập'));
$hc = $anyUnpaid(5);
tc('SV-09', 'Hủy hóa đơn: lý do tối thiểu 20 ký tự', 'BillingService', 'KTT hủy với lý do "Sai số"', 'Từ chối',
    function () use ($hc) { asUser('hapt'); BillingService::cancel((int)$hc['ID_hoa_don'], 'Sai số'); return 'Đã hủy'; }, $has('20 ký tự'));
tc('SV-10', 'Hủy hóa đơn mở khóa chỉ số, lập lại HĐ thay thế (-R1)', 'BillingService + TRIGGER', "KTT hủy {$hc['ma_hoa_don']}; Kế toán lập lại",
    'HĐ cũ Đã hủy, HĐ mới mã -R1 Chờ duyệt', function () use ($hc) {
        asUser('hapt');
        BillingService::cancel((int)$hc['ID_hoa_don'], 'Nhập sai chỉ số nước, đã đối chiếu lại đồng hồ');
        asUser('toannk');
        BillingService::issueBatch($hc['ma_can_ho'][0], $hc['ky_cuoc']);
        return DB::val("SELECT GROUP_CONCAT(CONCAT(ma_hoa_don,':',trang_thai_thanh_toan) ORDER BY ID_hoa_don SEPARATOR ', ') FROM hoa_don WHERE ID_can_ho=? AND ky_cuoc=?", [$hc['ID_can_ho'], $hc['ky_cuoc']]);
    }, fn($a) => str_contains($a, ':da_huy') && str_contains($a, '-R1:cho_duyet'));
$debtBefore = ReportService::kpi()['debt'];
tc('SV-11', 'Báo cáo công nợ loại trừ HĐ Đã hủy/Chờ duyệt', 'ReportService', 'So sánh công nợ với tổng HĐ chưa thanh toán', 'Bằng nhau, không tính HĐ hủy', fn() =>
    'KPI=' . money($debtBefore) . '; Σ chưa TT=' . money(DB::val("SELECT SUM(tong_tien) FROM hoa_don WHERE trang_thai_thanh_toan='chua_thanh_toan'"))
    . '; HĐ hủy=' . money(DB::val("SELECT SUM(tong_tien) FROM hoa_don WHERE trang_thai_thanh_toan='da_huy'")),
    fn($a) => preg_match('/KPI=(.+?); Σ chưa TT=(.+?);/', $a, $m) && $m[1] === $m[2]);

// ---- VNPay
$me = resident('A-1001');
$inv = $unpaid('A-1001');
tc('SV-12', 'Tạo URL thanh toán có chữ ký HMAC SHA512', 'PaymentService', "Cư dân A-1001 thanh toán {$inv['ma_hoa_don']}", 'URL chứa vnp_SecureHash 128 ký tự hex, GD khởi tạo',
    function () use ($me, $inv) {
        $u = PaymentService::create($me, (int)$inv['ID_hoa_don'], 'http://localhost/return');
        parse_str(parse_url($u, PHP_URL_QUERY), $q);
        return 'hash=' . strlen($q['vnp_SecureHash']) . ' ký tự; amount=' . $q['vnp_Amount'] . '; GD=' . DB::val("SELECT trang_thai FROM giao_dich_thanh_toan WHERE ma_giao_dich_ref=?", [$q['vnp_TxnRef']]);
    }, fn($a) => str_contains($a, 'hash=128') && str_contains($a, 'GD=khoi_tao'));
$gd = DB::one("SELECT * FROM giao_dich_thanh_toan WHERE ID_hoa_don=? AND trang_thai='khoi_tao' ORDER BY ID_giao_dich DESC LIMIT 1", [$inv['ID_hoa_don']]);
tc('SV-13', 'IPN sai chữ ký bị từ chối, đưa vào ngoại lệ', 'PaymentService', 'IPN có vnp_SecureHash bị sửa', 'RspCode 97; HĐ chưa thanh toán; GD ngoại lệ',
    function () use ($gd, $signIpn, $inv) {
        $q = $signIpn($gd); $q['vnp_SecureHash'] = str_repeat('a', 128);
        $r = PaymentService::ipn($q);
        return "RspCode {$r['RspCode']}; HĐ=" . DB::val("SELECT trang_thai_thanh_toan FROM hoa_don WHERE ID_hoa_don=?", [$inv['ID_hoa_don']]) . '; GD=' . DB::val("SELECT trang_thai FROM giao_dich_thanh_toan WHERE ID_giao_dich=?", [$gd['ID_giao_dich']]);
    }, $has('RspCode 97; HĐ=chua_thanh_toan; GD=ngoai_le'));
$me = resident('A-1001');
PaymentService::create($me, (int)$inv['ID_hoa_don'], 'http://localhost/return');
$gd = DB::one("SELECT * FROM giao_dich_thanh_toan WHERE ID_hoa_don=? AND trang_thai='khoi_tao' ORDER BY ID_giao_dich DESC LIMIT 1", [$inv['ID_hoa_don']]);
tc('SV-14', 'IPN sai số tiền bị từ chối', 'PaymentService', 'IPN vnp_Amount = 100 (đúng chữ ký)', 'RspCode 04', fn() => 'RspCode ' . PaymentService::ipn($signIpn($gd, '00', 100))['RspCode'], $has('RspCode 04'));
PaymentService::create($me, (int)$inv['ID_hoa_don'], 'http://localhost/return');
$gd = DB::one("SELECT * FROM giao_dich_thanh_toan WHERE ID_hoa_don=? AND trang_thai='khoi_tao' ORDER BY ID_giao_dich DESC LIMIT 1", [$inv['ID_hoa_don']]);
tc('SV-15', 'Cư dân hủy giao dịch (mã 24) – hóa đơn giữ nguyên', 'PaymentService', 'IPN ResponseCode 24', 'RspCode 00; GD thất bại; HĐ chưa thanh toán',
    fn() => 'RspCode ' . PaymentService::ipn($signIpn($gd, '24'))['RspCode'] . '; GD=' . DB::val("SELECT trang_thai FROM giao_dich_thanh_toan WHERE ID_giao_dich=?", [$gd['ID_giao_dich']])
        . '; HĐ=' . DB::val("SELECT trang_thai_thanh_toan FROM hoa_don WHERE ID_hoa_don=?", [$inv['ID_hoa_don']]), $has('RspCode 00; GD=that_bai; HĐ=chua_thanh_toan'));
PaymentService::create($me, (int)$inv['ID_hoa_don'], 'http://localhost/return');
$gd = DB::one("SELECT * FROM giao_dich_thanh_toan WHERE ID_hoa_don=? AND trang_thai='khoi_tao' ORDER BY ID_giao_dich DESC LIMIT 1", [$inv['ID_hoa_don']]);
tc('SV-16', 'IPN hợp lệ: gạch nợ tự động', 'PaymentService + TRIGGER', 'IPN ResponseCode 00, đúng chữ ký và số tiền', 'RspCode 00; HĐ đã thanh toán qua VNPay',
    fn() => 'RspCode ' . PaymentService::ipn($signIpn($gd))['RspCode'] . '; HĐ=' . implode('/', DB::one("SELECT trang_thai_thanh_toan, phuong_thuc_thanh_toan FROM hoa_don WHERE ID_hoa_don=?", [$inv['ID_hoa_don']])),
    $has('RspCode 00; HĐ=da_thanh_toan/vnpay'));
tc('SV-17', 'IPN gửi lại (trùng lặp) không gạch nợ 2 lần', 'PaymentService + UNIQUE', 'Gửi lại đúng IPN vừa xử lý', 'RspCode 02; vẫn 1 GD thành công',
    fn() => 'RspCode ' . PaymentService::ipn($signIpn($gd))['RspCode'] . '; số GD thành công=' . DB::val("SELECT COUNT(*) FROM giao_dich_thanh_toan WHERE ID_hoa_don=? AND trang_thai='thanh_cong'", [$inv['ID_hoa_don']]),
    $has('RspCode 02; số GD thành công=1'));
$h2 = $anyUnpaid(10);
$me2 = resident($h2['ma_can_ho']);
PaymentService::create($me2, (int)$h2['ID_hoa_don'], 'http://localhost/return');
$gd2 = DB::one("SELECT * FROM giao_dich_thanh_toan WHERE ID_hoa_don=? AND trang_thai='khoi_tao' ORDER BY ID_giao_dich DESC LIMIT 1", [$h2['ID_hoa_don']]);
tc('SV-18', 'Gạch nợ thủ công: số tiền phải khớp hóa đơn', 'PaymentService', "Kế toán thu 100.000 đ cho {$h2['ma_hoa_don']}", 'Từ chối',
    function () use ($h2) { asUser('toannk'); PaymentService::manual((int)$h2['ID_hoa_don'], 'tien_mat', 100000, 'Nguyễn Văn A', 'PT-TEST-1'); return 'Đã gạch nợ'; }, $has('phải bằng tổng tiền'));
tc('SV-19', 'Gạch nợ thủ công bắt buộc số chứng từ', 'PaymentService + CHECK ck_gd_thu_cong', 'Bỏ trống số phiếu thu', 'Từ chối',
    function () use ($h2) { asUser('toannk'); PaymentService::manual((int)$h2['ID_hoa_don'], 'tien_mat', (float)$h2['tong_tien'], 'Nguyễn Văn A', ''); return 'Đã gạch nợ'; }, $has('số phiếu thu'));
tc('SV-20', 'Gạch nợ thủ công hợp lệ', 'PaymentService', 'Đúng số tiền, có phiếu thu PT-TEST-2', 'HĐ đã thanh toán tiền mặt',
    function () use ($h2) { asUser('toannk'); PaymentService::manual((int)$h2['ID_hoa_don'], 'tien_mat', (float)$h2['tong_tien'], 'Nguyễn Văn A', 'PT-TEST-2');
        return implode('/', DB::one("SELECT trang_thai_thanh_toan, phuong_thuc_thanh_toan FROM hoa_don WHERE ID_hoa_don=?", [$h2['ID_hoa_don']])); }, $has('da_thanh_toan/tien_mat'));
tc('SV-21', 'IPN đến sau khi đã thu tiền mặt → ngoại lệ đối soát', 'PaymentService + UNIQUE', 'IPN thành công cho GD VNPay đang chờ của HĐ vừa thu tiền mặt', 'RspCode 02; GD ngoại lệ; không gạch nợ 2 lần',
    fn() => 'RspCode ' . PaymentService::ipn($signIpn($gd2))['RspCode'] . '; GD=' . DB::val("SELECT trang_thai FROM giao_dich_thanh_toan WHERE ID_giao_dich=?", [$gd2['ID_giao_dich']]),
    $has('RspCode 02; GD=ngoai_le'));
$ten = resident('A-1002', 'khach_thue');
$old = DB::one("SELECT * FROM hoa_don WHERE ID_can_ho=? AND ky_cuoc < ? AND trang_thai_thanh_toan IN ('chua_thanh_toan','da_thanh_toan') ORDER BY ky_cuoc LIMIT 1", [$ten['ID_can_ho'], substr($ten['ngay_bat_dau_hop_dong'], 0, 7)]);
tc('SV-22', 'Khách thuê chỉ thấy hóa đơn trong thời hạn hợp đồng', 'BillingService', "Khách thuê A-1002 (HĐ từ {$ten['ngay_bat_dau_hop_dong']}) xem HĐ kỳ {$old['ky_cuoc']}", 'Không thấy; không tạo được thanh toán',
    function () use ($ten, $old) {
        $ks = array_column(BillingService::visibleInvoices($ten), 'ky_cuoc');
        try { PaymentService::create($ten, (int)$old['ID_hoa_don'], 'x'); $p = 'tạo được'; } catch (BusinessException $e) { $p = $e->getMessage(); }
        return 'Kỳ thấy được: ' . min($ks) . '→' . max($ks) . '; thanh toán HĐ cũ: ' . $p;
    }, fn($a) => str_contains($a, 'ngoài thời hạn') && !str_contains($a, $old['ky_cuoc'] . '→'));

// ---- Tiện ích
$bbq = (int)DB::val("SELECT ID_tien_ich FROM dich_vu_tien_ich WHERE ma_tien_ich='BBQ'");
$pool = (int)DB::val("SELECT ID_tien_ich FROM dich_vu_tien_ich WHERE ma_tien_ich='POOL'");
$hall = (int)DB::val("SELECT ID_tien_ich FROM dich_vu_tien_ich WHERE ma_tien_ich='HALL'");
$u1 = cleanApt(0); $u2 = cleanApt(1);
tc('SV-23', 'Đặt tiện ích hợp lệ sinh vé QR', 'BookingService', "{$u1['ma_can_ho']} đặt BBQ $D3 10:00, 6 người", 'Thành công, mã vé MOONS-BBQ-...',
    fn() => 'Mã vé ' . BookingService::book($u1, $bbq, $D3, '10:00', 6)['code'], $has('MOONS-BBQ-'));
tc('SV-24', 'Chống trùng khung giờ (tiện ích độc quyền)', 'BookingService (FOR UPDATE)', "{$u2['ma_can_ho']} đặt cùng BBQ $D3 10:00", 'Từ chối',
    fn() => BookingService::book($u2, $bbq, $D3, '10:00', 4)['code'], $has('vừa được đăng ký'));
tc('SV-25', 'Đặt đồng thời cùng khung giờ: chỉ 1 giao dịch thành công', 'BookingService (khóa dòng InnoDB)', '2 tiến trình PHP cùng đặt BBQ 14:00, giữ khóa 0,4 giây',
    'Đúng 1 OK, 1 bị từ chối; CSDL chỉ có 1 lượt', function () use ($u1, $u2, $bbq, $D3) {
        putenv('MOONS_DB=' . DB::val("SELECT DATABASE()"));   // tiến trình con kế thừa biến môi trường (Windows và Linux)
        $cmd = fn($nk) => sprintf('%s %s %d %d %s 14:00', escapeshellarg(PHP_BINARY), escapeshellarg(__DIR__ . '/book_worker.php'), $nk, $bbq, $D3);
        $p1 = popen($cmd($u1['ID_nhan_khau']), 'r'); $p2 = popen($cmd($u2['ID_nhan_khau']), 'r');
        $o1 = stream_get_contents($p1); $o2 = stream_get_contents($p2); pclose($p1); pclose($p2);
        $n = DB::val("SELECT COUNT(*) FROM dat_tien_ich WHERE ID_tien_ich=? AND ngay_dat=? AND khung_gio_bat_dau='14:00:00' AND trang_thai_dat='da_xac_nhan'", [$bbq, $D3]);
        return "P1: " . substr($o1, 0, 60) . " | P2: " . substr($o2, 0, 60) . " | Số lượt trong CSDL: $n";
    }, fn($a) => substr_count($a, 'OK MOONS') === 1 && substr_count($a, 'ERR') === 1 && str_ends_with($a, ': 1'));
tc('SV-26', 'Hạn mức 2 lượt BBQ/căn hộ/tuần', 'BookingService', "{$u1['ma_can_ho']} đặt lượt BBQ thứ 3 trong tuần", 'Lượt thứ 3 bị từ chối', function () use ($u1, $bbq, $D3) {
    $free = fn() => array_values(array_filter(BookingService::slots($bbq, $D3), fn($s) => $s['state'] === 'trong'));
    while ((int)DB::val("SELECT COUNT(*) FROM dat_tien_ich WHERE ID_can_ho=? AND ID_tien_ich=? AND trang_thai_dat='da_xac_nhan'", [$u1['ID_can_ho'], $bbq]) < 2)
        BookingService::book($u1, $bbq, $D3, $free()[0]['start'], 4);
    return 'Đã có 2 lượt; lượt 3: ' . BookingService::book($u1, $bbq, $D3, $free()[0]['start'], 4)['code'];
}, $has('hết hạn mức'));
tc('SV-27', 'Vượt sức chứa bị từ chối', 'BookingService', "{$u2['ma_can_ho']} đặt BBQ 45 người (tối đa 40)", 'Từ chối',
    fn() => BookingService::book($u2, $bbq, $D3, '16:00', 45)['code'], $has('sức chứa'));
tc('SV-28', 'Không đặt ngày trong quá khứ', 'BookingService', 'Đặt hồ bơi ngày hôm qua', 'Từ chối',
    fn() => BookingService::book($u2, $pool, date('Y-m-d', strtotime(today() . ' -1 day')), '08:00', 2)['code'], $has('quá khứ'));
$debtor = DB::val("SELECT ch.ma_can_ho FROM can_ho ch JOIN hoa_don h ON h.ID_can_ho=ch.ID_can_ho WHERE ch.trang_thai_o='chu_ho_o' AND h.trang_thai_thanh_toan='chua_thanh_toan' AND h.han_thanh_toan < ? - INTERVAL 30 DAY LIMIT 1", [today()]);
$ud = resident($debtor);
tc('SV-29', 'Nợ quá hạn > 30 ngày bị khóa đặt tiện ích độc quyền', 'BookingService', "$debtor (có HĐ quá hạn) đặt BBQ", 'Từ chối',
    fn() => BookingService::book($ud, $bbq, $D3, '08:00', 4)['code'], $has('quá hạn trên 30 ngày'));
tc('SV-30', 'Căn nợ quá hạn vẫn được dùng tiện ích dùng chung (hồ bơi)', 'BookingService', "$debtor đặt hồ bơi", 'Thành công',
    fn() => 'Mã vé ' . BookingService::book($ud, $pool, $D3, '08:00', 2)['code'], $has('MOONS-POOL'));
tc('SV-31', 'Hủy muộn (< 2 giờ) vẫn tính vào hạn mức tuần', 'BookingService', 'Đặt BBQ hôm nay khung giờ sắp tới rồi hủy ngay', 'Trạng thái huy_muon', function () use ($u2, $bbq) {
    $slot = null;
    foreach (BookingService::slots($bbq, today()) as $s) if ($s['state'] === 'trong') { $slot = $s; break; }
    $date = today();
    if (!$slot || strtotime("$date {$slot['start']}") - time() >= 7200) {   // nếu không còn khung giờ trong 2 giờ tới: tạo dữ liệu trực tiếp
        $date = today(); $start = date('H:00:00', time() + 3600);
        DB::q("INSERT INTO dat_tien_ich(ma_ve,ngay_dat,khung_gio_bat_dau,khung_gio_ket_thuc,so_nguoi,ID_tien_ich,ID_nhan_khau,ID_can_ho,ID_nguoi_tao) VALUES ('TEST-SV31',?,?,?,4,?,?,?,?)",
            [$date, $start, date('H:00:00', time() + 3600 * 2 > strtotime('23:59') ? strtotime('23:59') : time() + 7200), $bbq, $u2['ID_nhan_khau'], $u2['ID_can_ho'], $u2['ID_tai_khoan']]);
        $id = DB::id();
    } else $id = BookingService::book($u2, $bbq, $date, $slot['start'], 4)['id'];
    $msg = BookingService::cancel($u2, (int)$id);
    return DB::val("SELECT trang_thai_dat FROM dat_tien_ich WHERE ID_dat_lich=?", [$id]) . ' – ' . $msg;
}, $has('huy_muon'));
tc('SV-32', 'Khóa lịch bảo trì tự hủy lịch trùng và chặn đặt mới', 'BookingService', "KTT khóa BBQ $D3 08:00–22:00", 'Hủy ≥1 lịch; đặt mới bị từ chối',
    function () use ($bbq, $D3, $u2) {
        asUser('hapt');
        $n = BookingService::maintenance($bbq, $D3, '08:00', '22:00');
        try { BookingService::book($u2, $bbq, $D3, '12:00', 4); $b = 'đặt được'; } catch (BusinessException $e) { $b = $e->getMessage(); }
        return "Đã hủy $n lịch; đặt mới: $b";
    }, fn($a) => preg_match('/Đã hủy [1-9]/', $a) && str_contains($a, 'bảo trì'));
tc('SV-33', 'Xác thực vé QR: vé không phải hôm nay bị từ chối', 'BookingService', 'Lễ tân quét vé của ngày khác', 'Từ chối', function () use ($pool, $D3) {
    asUser('tantl');
    return BookingService::verify(DB::val("SELECT ma_ve FROM dat_tien_ich WHERE ID_tien_ich=? AND ngay_dat=? AND trang_thai_dat='da_xac_nhan' LIMIT 1", [$pool, $D3]))['ma_ve'];
}, $has('không phải hôm nay'));

// ---- Thẻ xe
$full = DB::val("SELECT ch.ma_can_ho FROM can_ho ch JOIN the_xe x ON x.ID_can_ho=ch.ID_can_ho AND x.trang_thai_duyet='da_duyet' AND x.loai_xe='xe_may'
                 WHERE ch.trang_thai_o='chu_ho_o' GROUP BY ch.ID_can_ho HAVING COUNT(*)=2 LIMIT 1");
$uv = resident($full);
tc('SV-34', 'Định mức xe tính cả hồ sơ chờ duyệt (tối đa 4 xe máy)', 'VehicleService', "$full (2 xe máy) đăng ký thêm 3 xe máy", 'Xe thứ 5 bị từ chối', function () use ($uv) {
    $out = [];
    for ($i = 1; $i <= 3; $i++) {
        try { VehicleService::register($uv, ['loai_xe' => 'xe_may', 'bien_so_xe' => sprintf('59Z%d-888.%02d', $i, $i), 'nhan_hieu' => 'Honda', 'mau_xe' => 'Đen'], 'seed/ca_vet.jpg'); $out[] = "xe {$i}: OK"; }
        catch (BusinessException $e) { $out[] = "xe {$i}: " . $e->getMessage(); }
    }
    return implode('; ', $out);
}, fn($a) => str_contains($a, 'xe 1: OK') && str_contains($a, 'xe 2: OK') && str_contains($a, 'xe 3: Căn hộ đã đạt định mức'));
tc('SV-35', 'Biển số sai định dạng bị từ chối', 'VehicleService', "Biển '51G 12345 X'", 'Từ chối', function () use ($u1) {
    return (string)VehicleService::register($u1, ['loai_xe' => 'o_to', 'bien_so_xe' => '51G 12345 X', 'nhan_hieu' => 'Kia', 'mau_xe' => 'Trắng'], 'seed/ca_vet.jpg');
}, $has('không đúng định dạng'));
tc('SV-36', 'Phí gửi xe chỉ tính xe đã duyệt', 'BillingService', "Tính dòng phí xe cho $full (2 xe đã duyệt + 2 chờ duyệt)", 'Phí xe máy: 2 xe',
    fn() => implode('; ', array_map(fn($l) => $l['dien_giai'] . ' ' . num($l['so_luong']) . ' xe', array_filter(BillingService::computeLines((int)$uv['ID_can_ho'],
        ['chi_so_dien_cu' => 0, 'chi_so_dien_moi' => 10, 'chi_so_nuoc_cu' => 0, 'chi_so_nuoc_moi' => 1]), fn($l) => str_starts_with($l['loai_cuoc'], 'gui_xe')))), $has('xe máy 2 xe'));

// ---- Sự cố
$tkOther = DB::one("SELECT * FROM phieu_su_co WHERE trang_thai_kanban='da_phan_cong' AND ID_ky_thuat_vien <> (SELECT ID_tai_khoan FROM tai_khoan WHERE ten_dang_nhap='namhv') LIMIT 1");
tc('SV-37', 'Kỹ thuật viên chỉ cập nhật phiếu của mình', 'TicketService', "KTV namhv kéo phiếu {$tkOther['ma_phieu']} (của KTV khác)", 'Từ chối',
    function () use ($tkOther) { asUser('namhv'); TicketService::move((int)$tkOther['ID_phieu'], 'dang_xu_ly'); return 'Đã chuyển'; }, $has('chính mình'));
$tkMine = DB::one("SELECT p.* FROM phieu_su_co p JOIN tai_khoan t ON t.ID_tai_khoan=p.ID_ky_thuat_vien WHERE t.ten_dang_nhap='namhv' AND trang_thai_kanban='da_phan_cong' LIMIT 1");
tc('SV-38', 'Không nhảy cóc trạng thái Kanban', 'TicketService', "Chuyển {$tkMine['ma_phieu']} từ Đã phân công sang Hoàn tất", 'Từ chối',
    function () use ($tkMine) { asUser('namhv'); TicketService::move((int)$tkMine['ID_phieu'], 'hoan_tat'); return 'Đã chuyển'; }, $has('Không thể chuyển'));
tc('SV-39', 'Đề xuất nghiệm thu bắt buộc ảnh kết quả', 'TicketService + CHECK ck_psc_anh', 'KTV chuyển Đang xử lý rồi gửi nghiệm thu không có ảnh', 'Từ chối',
    function () use ($tkMine) { asUser('namhv'); TicketService::move((int)$tkMine['ID_phieu'], 'dang_xu_ly'); TicketService::submitAcceptance((int)$tkMine['ID_phieu'], null, '', 'Đã thay gioăng'); return 'Đã gửi'; },
    $has('ảnh nghiệm thu'));
tc('SV-40', 'Luồng nghiệm thu 3 bước hoàn chỉnh', 'TicketService', 'KTV gửi ảnh → cư dân chấm 5 sao', 'Phiếu Hoàn tất, có điểm và ngày hoàn thành', function () use ($tkMine) {
    asUser('namhv');
    TicketService::submitAcceptance((int)$tkMine['ID_phieu'], 'seed/nghiem_thu.jpg', '01 gioăng', 'Đã thay gioăng, hết rò rỉ');
    $me = DB::one("SELECT nk.*, ch.ma_can_ho FROM nhan_khau nk JOIN can_ho ch USING(ID_can_ho) WHERE ID_nhan_khau=?", [$tkMine['ID_nguoi_gui']]);
    TicketService::confirm($me, (int)$tkMine['ID_phieu'], 5);
    return implode(' / ', DB::one("SELECT trang_thai_kanban, diem_danh_gia, ngay_hoan_thanh IS NOT NULL ok FROM phieu_su_co WHERE ID_phieu=?", [$tkMine['ID_phieu']]));
}, $has('hoan_tat / 5 / 1'));
$tkNew = DB::one("SELECT * FROM phieu_su_co WHERE trang_thai_kanban='cho_tiep_nhan' LIMIT 1");
tc('SV-41', 'Chỉ Điều phối viên phân công, và chỉ cho kỹ thuật viên', 'TicketService', "Điều phối viên giao {$tkNew['ma_phieu']} cho tài khoản Lễ tân", 'Từ chối',
    function () use ($tkNew) { asUser('dieuvm'); TicketService::assign((int)$tkNew['ID_phieu'], (int)DB::val("SELECT ID_tai_khoan FROM tai_khoan WHERE ten_dang_nhap='tantl'")); return 'Đã giao'; }, $has('không hợp lệ'));

// ---- Tài khoản & nhân khẩu
tc('SV-42', 'Sai mật khẩu 5 lần khóa tạm 15 phút', 'AuthService', 'Đăng nhập kế toán sai mật khẩu 5 lần, lần 6 đúng mật khẩu', 'Lần 6 vẫn bị khóa', function () {
    for ($i = 0; $i < 5; $i++) { try { AuthService::attempt('toannk', 'sai-mat-khau'); } catch (BusinessException $e) {} }
    try { AuthService::attempt('toannk', 'Moons@123'); return 'Đăng nhập được'; } catch (BusinessException $e) { return $e->getMessage(); }
}, $has('tạm khóa'));
DB::q("UPDATE tai_khoan SET so_lan_sai=0, khoa_den=NULL WHERE ten_dang_nhap='toannk'");
$exp = DB::one("SELECT nk.*, tk.ten_dang_nhap FROM nhan_khau nk JOIN tai_khoan tk USING(ID_tai_khoan) WHERE loai_cu_dan='khach_thue' AND trang_thai_duyet='da_duyet' LIMIT 1");
DB::q("UPDATE nhan_khau SET ngay_bat_dau_hop_dong='2024-01-01', ngay_ket_thuc_hop_dong='2026-10-31' WHERE ID_nhan_khau=?", [$exp['ID_nhan_khau']]);
tc('SV-43', 'Hết hạn hợp đồng: thu hồi tài khoản khách thuê', 'AuthService + ResidentService', "Khách thuê {$exp['ten_dang_nhap']} (HĐ hết hạn 31/10/2026) đăng nhập",
    'Từ chối; tài khoản khóa; căn hộ cập nhật trạng thái', function () use ($exp) {
        try { AuthService::attempt($exp['ten_dang_nhap'], 'Moons@123'); $m = 'Đăng nhập được'; } catch (BusinessException $e) { $m = $e->getMessage(); }
        return $m . ' | TK trang_thai=' . DB::val("SELECT trang_thai FROM tai_khoan WHERE ten_dang_nhap=?", [$exp['ten_dang_nhap']]) . ' | căn hộ: ' . DB::val("SELECT trang_thai_o FROM can_ho WHERE ID_can_ho=?", [$exp['ID_can_ho']]);
    }, fn($a) => str_contains($a, 'hết hạn') && str_contains($a, 'trang_thai=0') && str_contains($a, 'chu_ho_o'));
$me = resident('A-1001');
tc('SV-44', 'Người từ 14 tuổi phải khai báo CCCD', 'ResidentService', 'Khai báo thành viên sinh 2000, không CCCD', 'Từ chối',
    fn() => (string)ResidentService::declare($me, ['loai_cu_dan' => 'thanh_vien', 'ho_ten' => 'Test Thành Viên', 'ngay_sinh' => '2000-01-01', 'quan_he_chu_ho' => 'Anh/Chị/Em',
        'gioi_tinh' => 'nam', 'so_cccd' => '', 'so_dien_thoai' => '', 'email' => ''], null), $has('bắt buộc khai báo số CCCD'));
tc('SV-45', 'Duyệt khách thuê cấp tài khoản mật khẩu tạm (bắt buộc đổi)', 'ResidentService', 'Chủ hộ A-1001 khai báo khách thuê, Lễ tân duyệt', 'Tạo tài khoản khach_thue, phai_doi_mat_khau=1',
    function () use ($me) {
        $id = ResidentService::declare($me, ['loai_cu_dan' => 'khach_thue', 'ho_ten' => 'Phan Khách Thuê', 'ngay_sinh' => '1995-05-05', 'quan_he_chu_ho' => 'x', 'gioi_tinh' => 'nu',
            'so_cccd' => '079195009999', 'so_dien_thoai' => '0911222333', 'email' => '', 'so_hop_dong' => 'HDT-TEST', 'ngay_bat_dau_hop_dong' => today(),
            'ngay_ket_thuc_hop_dong' => date('Y-m-d', strtotime('+1 year')), 'quan_he' => ''], 'seed/hop_dong.jpg');
        asUser('tantl');
        $acc = ResidentService::approve($id);
        return implode('/', DB::one("SELECT vai_tro, phai_doi_mat_khau FROM tai_khoan WHERE ten_dang_nhap=?", [$acc['username']])) . '; căn hộ: ' . DB::val("SELECT trang_thai_o FROM can_ho WHERE ma_can_ho='A-1001'");
    }, $has('khach_thue/1; căn hộ: cho_thue'));

// ---- Trợ lý AI
$me = resident('A-1001');
tc('SV-46', 'Truy xuất (Retrieval) đúng tài liệu nội quy', 'AiService', '"Giờ mở cửa hồ bơi?"', 'Nguồn: Nội quy sử dụng hồ bơi; có "06:00 đến 22:00"', function () use ($me) {
    $r = AiService::ask('Giờ mở cửa hồ bơi?', $me); return $r['answer'] . ' [Nguồn: ' . $r['source'] . ']';
}, fn($a) => str_contains($a, '06:00 đến 22:00') && str_contains($a, 'hồ bơi]'));
tc('SV-47', 'Câu hỏi hóa đơn riêng trả lời từ CSDL, không gửi mô hình AI', 'AiService', '"Hóa đơn tháng này của tôi"', 'mode=du_lieu_rieng, đúng số tiền HĐ mới nhất',
    function () use ($me) { $r = AiService::ask('Hóa đơn tháng này của tôi bao nhiêu?', $me); return "mode={$r['mode']}; " . $r['answer']; },
    fn($a) => str_contains($a, 'mode=du_lieu_rieng') && str_contains($a, money(BillingService::visibleInvoices(resident('A-1001'))[0]['tong_tien'])));
tc('SV-48', 'Không có ngữ cảnh liên quan thì không bịa câu trả lời', 'AiService', '"Giá vàng hôm nay?"', 'Báo chưa có thông tin, đề nghị liên hệ Lễ tân',
    fn() => AiService::ask('Giá vàng SJC hôm nay?', $me)['answer'], $has('chưa tìm thấy'));

/* ================= NHÓM PQ: phân quyền & bảo mật qua HTTP ================= */
if ($httpBase) {
    $http = function (string $user = null, string $route = '', string $method = 'GET', array $post = [], bool $csrf = true) use ($httpBase) {
        $jar = tempnam(sys_get_temp_dir(), 'ck');
        $req = function ($url, $data = null) use ($jar) {
            $c = curl_init($url);
            curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => ['Accept: text/html']]);
            if ($data !== null) { curl_setopt($c, CURLOPT_POST, true); curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($data)); }
            $r = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
            return [$code, $r];
        };
        [, $page] = $req("$httpBase?r=auth/login");
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $page, $m);
        $tok = $m[1] ?? '';
        if ($user) $req("$httpBase?r=auth/login", ['_csrf' => $tok, 'login' => $user, 'password' => 'Moons@123']);
        if ($method === 'POST') { [$code, $r] = $req("$httpBase?r=$route", ($csrf ? ['_csrf' => $tok] : []) + $post); }
        else [$code, $r] = $req("$httpBase?r=$route");
        preg_match('/^Location: (.*)$/mi', $r, $loc);
        return "HTTP $code" . (isset($loc[1]) ? ' → ' . trim($loc[1]) : '');
    };
    tc('PQ-01', 'Chưa đăng nhập bị chuyển về trang đăng nhập', 'Middleware Auth::guard', 'GET admin/dashboard (ẩn danh)', 'HTTP 302 → auth/login', fn() => $http(null, 'admin/dashboard'), $has('auth%2Flogin'));
    tc('PQ-02', 'Cư dân không truy cập phân hệ quản trị', 'Middleware RBAC', 'Chủ hộ 0981230001 GET admin/dashboard', 'HTTP 403', fn() => $http('0981230001', 'admin/dashboard'), $has('HTTP 403'));
    tc('PQ-03', 'Lễ tân không truy cập chỉ số & hóa đơn', 'Middleware RBAC', 'tantl GET admin/billing', 'HTTP 403', fn() => $http('tantl', 'admin/billing'), $has('HTTP 403'));
    tc('PQ-04', 'Kế toán không được phê duyệt phát hành', 'Middleware RBAC', 'toannk POST admin/invoices-approve', 'HTTP 403', fn() => $http('toannk', 'admin/invoices-approve', 'POST', ['ky' => '2026-10', 'tower' => 'C']), $has('HTTP 403'));
    tc('PQ-05', 'Kỹ thuật viên không vào Phân quyền & AI', 'Middleware RBAC', 'namhv GET admin/system', 'HTTP 403', fn() => $http('namhv', 'admin/system'), $has('HTTP 403'));
    tc('PQ-06', 'Admin không gạch nợ thủ công (tách biệt tài chính)', 'Middleware RBAC', 'trilq POST admin/payment-manual', 'HTTP 403', fn() => $http('trilq', 'admin/payment-manual', 'POST', ['invoice' => 1]), $has('HTTP 403'));
    tc('PQ-07', 'Yêu cầu POST thiếu CSRF token bị chặn', 'Csrf::check', 'Chủ hộ POST tickets/cancel không có _csrf', 'HTTP 419', fn() => $http('0981230001', 'tickets/cancel', 'POST', ['id' => 1], false), $has('HTTP 419'));
    tc('PQ-08', 'IPN công khai nhưng không giả mạo được', 'PaymentService', 'GET vnpay/ipn với chữ ký giả', 'RspCode 97', function () use ($httpBase) {
        return file_get_contents("$httpBase?r=vnpay/ipn&vnp_TxnRef=VNP1&vnp_Amount=100&vnp_ResponseCode=00&vnp_SecureHash=" . str_repeat('0', 128));
    }, $has('"97"'));
}

/* ================= TÍNH NHẤT QUÁN DỮ LIỆU SAU KIỂM THỬ ================= */
$checks = [
    'Hóa đơn có tong_tien ≠ Σ chi tiết' => "SELECT COUNT(*) FROM hoa_don h WHERE tong_tien <> (SELECT COALESCE(SUM(thanh_tien),0) FROM chi_tiet_hoa_don c WHERE c.ID_hoa_don=h.ID_hoa_don)",
    'Hóa đơn đã thanh toán không có GD thành công khớp tiền' => "SELECT COUNT(*) FROM hoa_don h WHERE trang_thai_thanh_toan='da_thanh_toan' AND NOT EXISTS (SELECT 1 FROM giao_dich_thanh_toan g WHERE g.ID_hoa_don=h.ID_hoa_don AND g.trang_thai='thanh_cong' AND g.so_tien=h.tong_tien)",
    'GD thành công nhưng hóa đơn chưa thanh toán' => "SELECT COUNT(*) FROM giao_dich_thanh_toan g JOIN hoa_don h USING(ID_hoa_don) WHERE g.trang_thai='thanh_cong' AND h.trang_thai_thanh_toan<>'da_thanh_toan'",
    'Căn hộ vượt định mức xe' => "SELECT COUNT(*) FROM (SELECT ID_can_ho FROM the_xe WHERE trang_thai_duyet IN ('cho_duyet','yeu_cau_bo_sung','da_duyet') GROUP BY ID_can_ho HAVING SUM(loai_xe='o_to')>2 OR SUM(loai_xe='xe_may')>4) x",
    'Lịch tiện ích độc quyền chồng giờ' => "SELECT COUNT(*) FROM dat_tien_ich a JOIN dat_tien_ich b ON a.ID_tien_ich=b.ID_tien_ich AND a.ngay_dat=b.ngay_dat AND a.ID_dat_lich<b.ID_dat_lich JOIN dich_vu_tien_ich t ON t.ID_tien_ich=a.ID_tien_ich WHERE t.doc_quyen=1 AND a.trang_thai_dat IN ('da_xac_nhan','da_su_dung') AND b.trang_thai_dat IN ('da_xac_nhan','da_su_dung') AND a.khung_gio_bat_dau<b.khung_gio_ket_thuc AND a.khung_gio_ket_thuc>b.khung_gio_bat_dau",
    'Chỉ số không nối tiếp kỳ trước' => "SELECT COUNT(*) FROM chi_so_dien_nuoc c JOIN chi_so_dien_nuoc p ON p.ID_can_ho=c.ID_can_ho AND p.ky_cuoc=DATE_FORMAT(STR_TO_DATE(CONCAT(c.ky_cuoc,'-01'),'%Y-%m-%d') - INTERVAL 1 MONTH,'%Y-%m') WHERE c.chi_so_dien_cu<>p.chi_so_dien_moi OR c.chi_so_nuoc_cu<>p.chi_so_nuoc_moi",
    'Hóa đơn đã phát hành nhưng chỉ số chưa khóa sổ' => "SELECT COUNT(*) FROM hoa_don h JOIN chi_so_dien_nuoc c ON c.ID_can_ho=h.ID_can_ho AND c.ky_cuoc=h.ky_cuoc WHERE h.trang_thai_thanh_toan IN ('chua_thanh_toan','da_thanh_toan') AND c.da_khoa_so=0",
    'Phiếu sự cố hoàn tất thiếu ảnh nghiệm thu' => "SELECT COUNT(*) FROM phieu_su_co WHERE trang_thai_kanban IN ('cho_nghiem_thu','hoan_tat') AND anh_nghiem_thu IS NULL",
];
$cons = [];
echo "\n=== Truy vấn kiểm tra tính nhất quán dữ liệu ===\n";
foreach ($checks as $k => $q) { $n = (int)DB::val($q); $cons[] = ['check' => $k, 'rows' => $n, 'sql' => $q]; printf("%-60s %d\n", $k, $n); }

$pass = count(array_filter($results, fn($r) => $r['pass']));
printf("\nTỔNG: %d/%d ca đạt\n", $pass, count($results));
file_put_contents(__DIR__ . '/results.json', json_encode(['date' => now(), 'db' => DB::val("SELECT DATABASE()"), 'version' => DB::val("SELECT VERSION()"),
    'php' => PHP_VERSION, 'results' => $results, 'consistency' => $cons], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
exit($pass === count($results) ? 0 : 1);
