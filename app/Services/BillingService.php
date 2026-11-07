<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Support\Db;
use App\Support\Rbac;
use PDOException;

/* ======================= CHỈ SỐ & HÓA ĐƠN (UC-ACC01, UC-ACC02, UC-ACC06) ======================= */
final class BillingService
{
    const ANOMALY_FACTOR = 3;   // tiêu thụ kỳ này > 3 lần trung bình 3 kỳ trước => cảnh báo
    const DUE_DAYS = 15;

    public static function prevKy(string $ky): string { return date('Y-m', strtotime($ky . '-01 -1 month')); }

    /** Trung bình tiêu thụ 3 kỳ liền trước (null nếu chưa đủ dữ liệu) */
    public static function avg3(int $canHo, string $ky): ?array
    {
        $r = Db::one("SELECT COUNT(*) n, AVG(chi_so_dien_moi-chi_so_dien_cu) d, AVG(chi_so_nuoc_moi-chi_so_nuoc_cu) w
                        FROM (SELECT * FROM chi_so_dien_nuoc WHERE ID_can_ho=? AND ky_cuoc<? ORDER BY ky_cuoc DESC LIMIT 3) t",
            [$canHo, $ky]);
        return (int)$r['n'] > 0 ? ['d' => (float)$r['d'], 'w' => (float)$r['w']] : null;
    }
    public static function isAnomaly(?array $avg, int $dien, int $nuoc): bool
    {
        if (!$avg) return false;
        return ($avg['d'] > 0 && $dien > self::ANOMALY_FACTOR * $avg['d']) || ($avg['w'] > 0 && $nuoc > self::ANOMALY_FACTOR * $avg['w']);
    }

    /** Bảng nhập liệu cho 1 tháp/kỳ: chỉ số cũ lấy từ chỉ số mới của kỳ trước */
    public static function sheet(string $tower, string $ky): array
    {
        $prev = self::prevKy($ky);
        $rows = Db::all("SELECT ch.ID_can_ho, ch.ma_can_ho, ch.so_tang,
                (SELECT ho_ten FROM nhan_khau nk WHERE nk.ID_can_ho=ch.ID_can_ho AND nk.trang_thai_duyet='da_duyet'
                   AND nk.loai_cu_dan IN ('chu_ho','khach_thue') ORDER BY nk.loai_cu_dan='khach_thue' DESC LIMIT 1) cu_dan,
                p.chi_so_dien_moi prev_d, p.chi_so_nuoc_moi prev_w,
                (p.chi_so_dien_moi-p.chi_so_dien_cu) prev_use_d, (p.chi_so_nuoc_moi-p.chi_so_nuoc_cu) prev_use_w,
                c.ID_chi_so, c.chi_so_dien_cu, c.chi_so_dien_moi, c.chi_so_nuoc_cu, c.chi_so_nuoc_moi, c.ghi_chu_xac_minh, c.da_khoa_so,
                h.ma_hoa_don, h.trang_thai_thanh_toan, h.tong_tien
              FROM can_ho ch
              LEFT JOIN chi_so_dien_nuoc p ON p.ID_can_ho=ch.ID_can_ho AND p.ky_cuoc=?
              LEFT JOIN chi_so_dien_nuoc c ON c.ID_can_ho=ch.ID_can_ho AND c.ky_cuoc=?
              LEFT JOIN hoa_don h ON h.ID_can_ho=ch.ID_can_ho AND h.ky_hieu_luc=?
             WHERE ch.thap_nha=? AND ch.trang_thai_o<>'trong'
             ORDER BY ch.so_tang, ch.ma_can_ho", [$prev, $ky, $ky, $tower]);
        foreach ($rows as &$r) {
            $r['old_d'] = $r['chi_so_dien_cu'] ?? $r['prev_d'] ?? 0;
            $r['old_w'] = $r['chi_so_nuoc_cu'] ?? $r['prev_w'] ?? 0;
            $r['avg'] = self::avg3((int)$r['ID_can_ho'], $ky);
            $r['anomaly'] = $r['ID_chi_so'] ? self::isAnomaly($r['avg'], $r['chi_so_dien_moi'] - $r['chi_so_dien_cu'],
                $r['chi_so_nuoc_moi'] - $r['chi_so_nuoc_cu']) : false;
        }
        return $rows;
    }

    /** Lưu chỉ số hàng loạt; trả về lỗi theo từng căn hộ (dòng hợp lệ vẫn được lưu) */
    public static function saveReadings(string $ky, array $input): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ky)) throw new BusinessException('Kỳ cước không hợp lệ.');
        $errors = [];
        $saved = 0;
        foreach ($input as $canHo => $v) {
            $canHo = (int)$canHo;
            if (($v['dien'] ?? '') === '' && ($v['nuoc'] ?? '') === '') continue;
            $ma = Db::val("SELECT ma_can_ho FROM can_ho WHERE ID_can_ho=?", [$canHo]);
            try {
                if (!ctype_digit((string)$v['dien']) || !ctype_digit((string)$v['nuoc']))
                    throw new BusinessException('Chỉ số phải là số nguyên không âm.');
                $cur = Db::one("SELECT * FROM chi_so_dien_nuoc WHERE ID_can_ho=? AND ky_cuoc=?", [$canHo, $ky]);
                if ($cur && $cur['da_khoa_so']) throw new BusinessException('Chỉ số kỳ này đã khóa sổ.');
                $prev = Db::one("SELECT * FROM chi_so_dien_nuoc WHERE ID_can_ho=? AND ky_cuoc=?", [$canHo, self::prevKy($ky)]);
                $oldD = $cur['chi_so_dien_cu'] ?? $prev['chi_so_dien_moi'] ?? 0;
                $oldW = $cur['chi_so_nuoc_cu'] ?? $prev['chi_so_nuoc_moi'] ?? 0;
                $d = (int)$v['dien'];
                $w = (int)$v['nuoc'];
                if ($d < $oldD) throw new BusinessException("Chỉ số điện mới ($d) nhỏ hơn chỉ số cũ ($oldD).");
                if ($w < $oldW) throw new BusinessException("Chỉ số nước mới ($w) nhỏ hơn chỉ số cũ ($oldW).");
                $note = trim($v['ghi_chu'] ?? '') ?: null;
                if (self::isAnomaly(self::avg3($canHo, $ky), $d - $oldD, $w - $oldW) && mb_strlen((string)$note) < 10)
                    throw new BusinessException('Tiêu thụ tăng vọt trên 300%. Nhập ghi chú xác nhận đã kiểm tra công tơ (≥10 ký tự).');
                if ($cur) {
                    Db::q("UPDATE chi_so_dien_nuoc SET chi_so_dien_moi=?, chi_so_nuoc_moi=?, ghi_chu_xac_minh=?, ngay_nhap=?, ID_nguoi_nhap=?
                           WHERE ID_chi_so=?", [$d, $w, $note, app_now(), Rbac::id(), $cur['ID_chi_so']]);
                } else {
                    Db::q("INSERT INTO chi_so_dien_nuoc(ky_cuoc, chi_so_dien_cu, chi_so_dien_moi, chi_so_nuoc_cu, chi_so_nuoc_moi,
                             ghi_chu_xac_minh, ID_can_ho, ID_nguoi_nhap) VALUES (?,?,?,?,?,?,?,?)",
                        [$ky, $oldD, $d, $oldW, $w, $note, $canHo, Rbac::id()]);
                }
                $saved++;
            } catch (BusinessException $e) {
                $errors[$canHo] = "$ma: " . $e->getMessage();
            } catch (PDOException $e) {
                $errors[$canHo] = "$ma: " . db_error_message($e);
            }
        }
        if ($saved) AuditService::log('nhap_chi_so', 'chi_so_dien_nuoc', $ky, "$saved căn hộ");
        return ['saved' => $saved, 'errors' => $errors];
    }

    /** Tính các dòng cước cho 1 căn hộ trong kỳ */
    public static function computeLines(int $canHo, array $reading, ?array $fees = null): array
    {
        $fees = $fees ?? FeeService::all();
        $ch = Db::one("SELECT * FROM can_ho WHERE ID_can_ho=?", [$canHo]);
        $lines = FeeService::electricTiers($reading['chi_so_dien_moi'] - $reading['chi_so_dien_cu'], $fees);
        $m3 = $reading['chi_so_nuoc_moi'] - $reading['chi_so_nuoc_cu'];
        $lines[] = ['loai_cuoc' => 'nuoc', 'dien_giai' => "Nước sinh hoạt ({$reading['chi_so_nuoc_cu']} → {$reading['chi_so_nuoc_moi']})",
            'so_luong' => $m3, 'don_gia' => (float)$fees['NUOC']['don_gia'], 'thanh_tien' => round($m3 * $fees['NUOC']['don_gia'])];
        $lines[] = ['loai_cuoc' => 'quan_ly', 'dien_giai' => 'Phí quản lý theo diện tích (m²)',
            'so_luong' => (float)$ch['dien_tich'], 'don_gia' => (float)$fees['QUAN_LY']['don_gia'],
            'thanh_tien' => round((float)$ch['dien_tich'] * $fees['QUAN_LY']['don_gia'])];
        foreach (['o_to' => ['XE_O_TO', 'gui_xe_o_to'], 'xe_may' => ['XE_MAY', 'gui_xe_may']] as $t => [$code, $loai]) {
            $n = VehicleService::used($canHo, $t, null, true);
            if ($n) $lines[] = ['loai_cuoc' => $loai, 'dien_giai' => 'Phí gửi xe ' . mb_strtolower(lbl('loai_xe', $t)),
                'so_luong' => $n, 'don_gia' => (float)$fees[$code]['don_gia'], 'thanh_tien' => round($n * $fees[$code]['don_gia'])];
        }
        return $lines;
    }

    /** Kế toán lập hóa đơn hàng loạt cho 1 tháp (trạng thái Chờ duyệt) */
    public static function issueBatch(string $tower, string $ky): array
    {
        $fees = FeeService::all();
        $created = 0;
        $skipped = [];
        $apts = Db::all("SELECT ch.ID_can_ho, ch.ma_can_ho, c.*
                           FROM can_ho ch JOIN chi_so_dien_nuoc c ON c.ID_can_ho=ch.ID_can_ho AND c.ky_cuoc=?
                          WHERE ch.thap_nha=? AND ch.trang_thai_o<>'trong'
                            AND NOT EXISTS (SELECT 1 FROM hoa_don h WHERE h.ID_can_ho=ch.ID_can_ho AND h.ky_hieu_luc=?)",
            [$ky, $tower, $ky]);
        $missing = (int)Db::val("SELECT COUNT(*) FROM can_ho ch WHERE thap_nha=? AND trang_thai_o<>'trong'
              AND NOT EXISTS (SELECT 1 FROM chi_so_dien_nuoc c WHERE c.ID_can_ho=ch.ID_can_ho AND c.ky_cuoc=?)", [$tower, $ky]);
        foreach ($apts as $a) {
            if (self::isAnomaly(self::avg3((int)$a['ID_can_ho'], $ky), $a['chi_so_dien_moi'] - $a['chi_so_dien_cu'],
                    $a['chi_so_nuoc_moi'] - $a['chi_so_nuoc_cu']) && !$a['ghi_chu_xac_minh']) {
                $skipped[] = "{$a['ma_can_ho']}: tiêu thụ bất thường chưa có ghi chú xác minh";
                continue;
            }
            try {
                Db::tx(function () use ($a, $ky, $fees) {
                    $suffix = (int)Db::val("SELECT COUNT(*) FROM hoa_don WHERE ID_can_ho=? AND ky_cuoc=?", [$a['ID_can_ho'], $ky]);
                    $ma = 'HD-' . str_replace('-', '', $ky) . '-' . str_replace('-', '', $a['ma_can_ho']) . ($suffix ? "-R$suffix" : '');
                    Db::q("INSERT INTO hoa_don(ma_hoa_don, ky_cuoc, ID_can_ho, ID_nguoi_lap) VALUES (?,?,?,?)",
                        [$ma, $ky, $a['ID_can_ho'], Rbac::id()]);
                    $hd = Db::id();
                    foreach (self::computeLines((int)$a['ID_can_ho'], $a, $fees) as $l) {
                        Db::q("INSERT INTO chi_tiet_hoa_don(loai_cuoc, dien_giai, so_luong, don_gia, thanh_tien, ID_hoa_don)
                               VALUES (?,?,?,?,?,?)", [$l['loai_cuoc'], $l['dien_giai'], $l['so_luong'], $l['don_gia'], $l['thanh_tien'], $hd]);
                    }
                });
                $created++;
            } catch (PDOException $e) {
                $skipped[] = "{$a['ma_can_ho']}: " . db_error_message($e);
            }
        }
        if ($missing) $skipped[] = "$missing căn hộ chưa nhập chỉ số kỳ này";
        AuditService::log('lap_hoa_don', 'hoa_don', "$tower/$ky", "$created hóa đơn chờ duyệt");
        return ['created' => $created, 'skipped' => $skipped];
    }

    /** Kế toán trưởng phê duyệt phát hành (phân tách nhiệm vụ: không duyệt hóa đơn do chính mình lập) */
    public static function approveBatch(string $tower, string $ky): int
    {
        if (!Rbac::is('ke_toan_truong')) throw new BusinessException('Chỉ Kế toán trưởng được phê duyệt phát hành hóa đơn.');
        return Db::tx(function () use ($tower, $ky) {
            $ids = Db::all("SELECT h.ID_hoa_don, h.ID_can_ho, h.ID_nguoi_lap FROM hoa_don h JOIN can_ho ch ON ch.ID_can_ho=h.ID_can_ho
                             WHERE ch.thap_nha=? AND h.ky_cuoc=? AND h.trang_thai_thanh_toan='cho_duyet' FOR UPDATE", [$tower, $ky]);
            $n = 0;
            foreach ($ids as $h) {
                if ((int)$h['ID_nguoi_lap'] === Rbac::id()) continue;
                Db::q("UPDATE hoa_don SET trang_thai_thanh_toan='chua_thanh_toan', ngay_phat_hanh=?, han_thanh_toan=?, ID_nguoi_duyet=?
                       WHERE ID_hoa_don=?", [app_now(), date('Y-m-d', strtotime(app_today() . ' +' . self::DUE_DAYS . ' days')), Rbac::id(), $h['ID_hoa_don']]);
                Db::q("UPDATE chi_so_dien_nuoc SET da_khoa_so=1 WHERE ID_can_ho=? AND ky_cuoc=? AND da_khoa_so=0", [$h['ID_can_ho'], $ky]);
                $n++;
            }
            if (!$n) throw new BusinessException('Không có hóa đơn chờ duyệt hợp lệ (hoặc hóa đơn do chính bạn lập).');
            AuditService::log('phe_duyet_phat_hanh', 'hoa_don', "$tower/$ky", "$n hóa đơn");
            return $n;
        });
    }

    /** Kế toán trưởng hủy hóa đơn (kèm lý do ≥20 ký tự). Chỉ số của kỳ được mở khóa để điều chỉnh và lập lại */
    public static function cancel(int $id, string $reason): void
    {
        if (!Rbac::is('ke_toan_truong')) throw new BusinessException('Chỉ Kế toán trưởng được hủy hóa đơn.');
        if (mb_strlen($reason) < 20) throw new BusinessException('Lý do hủy phải dài tối thiểu 20 ký tự.');
        Db::tx(function () use ($id, $reason) {
            $h = Db::one("SELECT * FROM hoa_don WHERE ID_hoa_don=? FOR UPDATE", [$id]);
            if (!$h || !in_array($h['trang_thai_thanh_toan'], ['cho_duyet', 'chua_thanh_toan'], true))
                throw new BusinessException('Chỉ hủy được hóa đơn chờ duyệt hoặc chưa thanh toán.');
            if (Db::val("SELECT COUNT(*) FROM giao_dich_thanh_toan WHERE ID_hoa_don=? AND trang_thai='khoi_tao'
                         AND ngay_giao_dich > NOW() - INTERVAL 15 MINUTE", [$id]))
                throw new BusinessException('Hóa đơn đang có giao dịch VNPay chờ xử lý, thử lại sau 15 phút.');
            Db::q("UPDATE hoa_don SET trang_thai_thanh_toan='da_huy', ly_do_huy=? WHERE ID_hoa_don=?", [$reason, $id]);
            Db::q("UPDATE chi_so_dien_nuoc SET da_khoa_so=0 WHERE ID_can_ho=? AND ky_cuoc=?", [$h['ID_can_ho'], $h['ky_cuoc']]);
            AuditService::log('huy_hoa_don', 'hoa_don', $h['ma_hoa_don'], $reason);
        });
    }

    /** Hóa đơn cư dân được xem. Khách thuê chỉ thấy kỳ nằm trong thời hạn hợp đồng */
    public static function visibleInvoices(array $me): array
    {
        $sql = "SELECT * FROM hoa_don WHERE ID_can_ho=? AND trang_thai_thanh_toan IN ('chua_thanh_toan','da_thanh_toan')";
        $p = [$me['ID_can_ho']];
        if ($me['loai_cu_dan'] === 'khach_thue') {
            $sql .= " AND ky_cuoc BETWEEN ? AND ?";
            $p[] = substr($me['ngay_bat_dau_hop_dong'], 0, 7);
            $p[] = substr($me['ngay_ket_thuc_hop_dong'], 0, 7);
        }
        return Db::all($sql . " ORDER BY ky_cuoc DESC", $p);
    }
    public static function findVisible(array $me, int $id): ?array
    {
        foreach (self::visibleInvoices($me) as $h) if ((int)$h['ID_hoa_don'] === $id) return $h;
        return null;
    }
    /** Căn hộ có hóa đơn quá hạn trên 30 ngày => khóa quyền đặt tiện ích độc quyền */
    public static function overdue30(int $canHo): int
    {
        return (int)Db::val("SELECT COUNT(*) FROM hoa_don WHERE ID_can_ho=? AND trang_thai_thanh_toan='chua_thanh_toan'
                             AND han_thanh_toan < ? - INTERVAL 30 DAY", [$canHo, app_today()]);
    }
}
