<?php
/* ======================= BIỂU PHÍ (UC-ADM02) ======================= */
final class FeeService
{
    public static function all(): array
    {
        $r = [];
        foreach (DB::all("SELECT * FROM bieu_phi ORDER BY ID_bieu_phi") as $f) $r[$f['ma_phi']] = $f;
        return $r;
    }
    public static function update(array $prices): void
    {
        if (!Auth::is('ke_toan_truong')) throw new BusinessException('Chỉ Kế toán trưởng được phê duyệt thay đổi biểu phí.');
        DB::tx(function () use ($prices) {
            foreach ($prices as $code => $v) {
                $v = (float)str_replace(['.', ','], ['', '.'], (string)$v);
                if ($v < 0) throw new BusinessException('Đơn giá không được âm.');
                $old = DB::val("SELECT don_gia FROM bieu_phi WHERE ma_phi=?", [$code]);
                if ($old === null || (float)$old === $v) continue;
                DB::q("UPDATE bieu_phi SET don_gia=?, ngay_cap_nhat=?, ID_nguoi_cap_nhat=? WHERE ma_phi=?", [$v, now(), Auth::id(), $code]);
                AuditService::log('cap_nhat_bieu_phi', 'bieu_phi', $code, num($old) . ' -> ' . num($v));
            }
        });
    }
    /** Tách tiền điện theo bậc thang: mỗi bậc là 1 dòng chi tiết (so_luong × don_gia) */
    public static function electricTiers(int $kwh, ?array $fees = null): array
    {
        $fees = $fees ?? self::all();
        $lines = [];
        $remain = $kwh;
        $prevCap = 0;   // bậc n bao gồm (den_muc bậc n-1, den_muc bậc n]
        for ($b = 1; $b <= 6 && $remain > 0; $b++) {
            $f = $fees["DIEN_B$b"];
            $cap = $f['den_muc'] === null ? PHP_INT_MAX : (int)$f['den_muc'];
            $q = min($remain, $cap - $prevCap);
            $lines[] = ['loai_cuoc' => 'dien', 'dien_giai' => "Điện bậc $b ({$f['tu_muc']}" . ($f['den_muc'] ? "–{$f['den_muc']}" : '+') . " kWh)",
                'so_luong' => $q, 'don_gia' => (float)$f['don_gia'], 'thanh_tien' => round($q * (float)$f['don_gia'])];
            $remain -= $q;
            $prevCap = $cap;
        }
        return $lines;
    }
}

/* ======================= CHỈ SỐ & HÓA ĐƠN (UC-ACC01, UC-ACC02, UC-ACC06) ======================= */
final class BillingService
{
    const ANOMALY_FACTOR = 3;   // tiêu thụ kỳ này > 3 lần trung bình 3 kỳ trước => cảnh báo
    const DUE_DAYS = 15;

    public static function prevKy(string $ky): string { return date('Y-m', strtotime($ky . '-01 -1 month')); }

    /** Trung bình tiêu thụ 3 kỳ liền trước (null nếu chưa đủ dữ liệu) */
    public static function avg3(int $canHo, string $ky): ?array
    {
        $r = DB::one("SELECT COUNT(*) n, AVG(chi_so_dien_moi-chi_so_dien_cu) d, AVG(chi_so_nuoc_moi-chi_so_nuoc_cu) w
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
        $rows = DB::all("SELECT ch.ID_can_ho, ch.ma_can_ho, ch.so_tang,
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
            $ma = DB::val("SELECT ma_can_ho FROM can_ho WHERE ID_can_ho=?", [$canHo]);
            try {
                if (!ctype_digit((string)$v['dien']) || !ctype_digit((string)$v['nuoc']))
                    throw new BusinessException('Chỉ số phải là số nguyên không âm.');
                $cur = DB::one("SELECT * FROM chi_so_dien_nuoc WHERE ID_can_ho=? AND ky_cuoc=?", [$canHo, $ky]);
                if ($cur && $cur['da_khoa_so']) throw new BusinessException('Chỉ số kỳ này đã khóa sổ.');
                $prev = DB::one("SELECT * FROM chi_so_dien_nuoc WHERE ID_can_ho=? AND ky_cuoc=?", [$canHo, self::prevKy($ky)]);
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
                    DB::q("UPDATE chi_so_dien_nuoc SET chi_so_dien_moi=?, chi_so_nuoc_moi=?, ghi_chu_xac_minh=?, ngay_nhap=?, ID_nguoi_nhap=?
                           WHERE ID_chi_so=?", [$d, $w, $note, now(), Auth::id(), $cur['ID_chi_so']]);
                } else {
                    DB::q("INSERT INTO chi_so_dien_nuoc(ky_cuoc, chi_so_dien_cu, chi_so_dien_moi, chi_so_nuoc_cu, chi_so_nuoc_moi,
                             ghi_chu_xac_minh, ID_can_ho, ID_nguoi_nhap) VALUES (?,?,?,?,?,?,?,?)",
                        [$ky, $oldD, $d, $oldW, $w, $note, $canHo, Auth::id()]);
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
        $ch = DB::one("SELECT * FROM can_ho WHERE ID_can_ho=?", [$canHo]);
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
        $apts = DB::all("SELECT ch.ID_can_ho, ch.ma_can_ho, c.*
                           FROM can_ho ch JOIN chi_so_dien_nuoc c ON c.ID_can_ho=ch.ID_can_ho AND c.ky_cuoc=?
                          WHERE ch.thap_nha=? AND ch.trang_thai_o<>'trong'
                            AND NOT EXISTS (SELECT 1 FROM hoa_don h WHERE h.ID_can_ho=ch.ID_can_ho AND h.ky_hieu_luc=?)",
            [$ky, $tower, $ky]);
        $missing = (int)DB::val("SELECT COUNT(*) FROM can_ho ch WHERE thap_nha=? AND trang_thai_o<>'trong'
              AND NOT EXISTS (SELECT 1 FROM chi_so_dien_nuoc c WHERE c.ID_can_ho=ch.ID_can_ho AND c.ky_cuoc=?)", [$tower, $ky]);
        foreach ($apts as $a) {
            if (self::isAnomaly(self::avg3((int)$a['ID_can_ho'], $ky), $a['chi_so_dien_moi'] - $a['chi_so_dien_cu'],
                    $a['chi_so_nuoc_moi'] - $a['chi_so_nuoc_cu']) && !$a['ghi_chu_xac_minh']) {
                $skipped[] = "{$a['ma_can_ho']}: tiêu thụ bất thường chưa có ghi chú xác minh";
                continue;
            }
            try {
                DB::tx(function () use ($a, $ky, $fees) {
                    $suffix = (int)DB::val("SELECT COUNT(*) FROM hoa_don WHERE ID_can_ho=? AND ky_cuoc=?", [$a['ID_can_ho'], $ky]);
                    $ma = 'HD-' . str_replace('-', '', $ky) . '-' . str_replace('-', '', $a['ma_can_ho']) . ($suffix ? "-R$suffix" : '');
                    DB::q("INSERT INTO hoa_don(ma_hoa_don, ky_cuoc, ID_can_ho, ID_nguoi_lap) VALUES (?,?,?,?)",
                        [$ma, $ky, $a['ID_can_ho'], Auth::id()]);
                    $hd = DB::id();
                    foreach (self::computeLines((int)$a['ID_can_ho'], $a, $fees) as $l) {
                        DB::q("INSERT INTO chi_tiet_hoa_don(loai_cuoc, dien_giai, so_luong, don_gia, thanh_tien, ID_hoa_don)
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
        if (!Auth::is('ke_toan_truong')) throw new BusinessException('Chỉ Kế toán trưởng được phê duyệt phát hành hóa đơn.');
        return DB::tx(function () use ($tower, $ky) {
            $ids = DB::all("SELECT h.ID_hoa_don, h.ID_can_ho, h.ID_nguoi_lap FROM hoa_don h JOIN can_ho ch ON ch.ID_can_ho=h.ID_can_ho
                             WHERE ch.thap_nha=? AND h.ky_cuoc=? AND h.trang_thai_thanh_toan='cho_duyet' FOR UPDATE", [$tower, $ky]);
            $n = 0;
            foreach ($ids as $h) {
                if ((int)$h['ID_nguoi_lap'] === Auth::id()) continue;
                DB::q("UPDATE hoa_don SET trang_thai_thanh_toan='chua_thanh_toan', ngay_phat_hanh=?, han_thanh_toan=?, ID_nguoi_duyet=?
                       WHERE ID_hoa_don=?", [now(), date('Y-m-d', strtotime(today() . ' +' . self::DUE_DAYS . ' days')), Auth::id(), $h['ID_hoa_don']]);
                DB::q("UPDATE chi_so_dien_nuoc SET da_khoa_so=1 WHERE ID_can_ho=? AND ky_cuoc=? AND da_khoa_so=0", [$h['ID_can_ho'], $ky]);
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
        if (!Auth::is('ke_toan_truong')) throw new BusinessException('Chỉ Kế toán trưởng được hủy hóa đơn.');
        if (mb_strlen($reason) < 20) throw new BusinessException('Lý do hủy phải dài tối thiểu 20 ký tự.');
        DB::tx(function () use ($id, $reason) {
            $h = DB::one("SELECT * FROM hoa_don WHERE ID_hoa_don=? FOR UPDATE", [$id]);
            if (!$h || !in_array($h['trang_thai_thanh_toan'], ['cho_duyet', 'chua_thanh_toan'], true))
                throw new BusinessException('Chỉ hủy được hóa đơn chờ duyệt hoặc chưa thanh toán.');
            if (DB::val("SELECT COUNT(*) FROM giao_dich_thanh_toan WHERE ID_hoa_don=? AND trang_thai='khoi_tao'
                         AND ngay_giao_dich > NOW() - INTERVAL 15 MINUTE", [$id]))
                throw new BusinessException('Hóa đơn đang có giao dịch VNPay chờ xử lý, thử lại sau 15 phút.');
            DB::q("UPDATE hoa_don SET trang_thai_thanh_toan='da_huy', ly_do_huy=? WHERE ID_hoa_don=?", [$reason, $id]);
            DB::q("UPDATE chi_so_dien_nuoc SET da_khoa_so=0 WHERE ID_can_ho=? AND ky_cuoc=?", [$h['ID_can_ho'], $h['ky_cuoc']]);
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
        return DB::all($sql . " ORDER BY ky_cuoc DESC", $p);
    }
    public static function findVisible(array $me, int $id): ?array
    {
        foreach (self::visibleInvoices($me) as $h) if ((int)$h['ID_hoa_don'] === $id) return $h;
        return null;
    }
    /** Căn hộ có hóa đơn quá hạn trên 30 ngày => khóa quyền đặt tiện ích độc quyền */
    public static function overdue30(int $canHo): int
    {
        return (int)DB::val("SELECT COUNT(*) FROM hoa_don WHERE ID_can_ho=? AND trang_thai_thanh_toan='chua_thanh_toan'
                             AND han_thanh_toan < ? - INTERVAL 30 DAY", [$canHo, today()]);
    }
}

/* ======================= THANH TOÁN VNPay & GẠCH NỢ (UC-RES06, UC-ACC03, UC-ACC04) ======================= */
final class PaymentService
{
    public static function mockMode(): bool { return cfg('vnpay')['tmn_code'] === ''; }

    /** Chuỗi ký theo đặc tả VNPay 2.1.0: sắp xếp khóa, urlencode, HMAC-SHA512 */
    public static function sign(array $params): string
    {
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);
        ksort($params);
        $data = [];
        foreach ($params as $k => $v) if ($v !== '' && $v !== null) $data[] = urlencode($k) . '=' . urlencode((string)$v);
        return hash_hmac('sha512', implode('&', $data), cfg('vnpay')['hash_secret']);
    }

    /** Tạo giao dịch và URL thanh toán */
    public static function create(array $me, int $invoiceId, string $returnUrl): string
    {
        $h = BillingService::findVisible($me, $invoiceId);
        if (!$h) throw new BusinessException('Không tìm thấy hóa đơn hoặc hóa đơn ngoài thời hạn hợp đồng thuê.');
        if ($h['trang_thai_thanh_toan'] !== 'chua_thanh_toan') throw new BusinessException('Hóa đơn không ở trạng thái chưa thanh toán.');
        $ref = 'VNP' . date('ymdHis') . random_int(1000, 9999);
        DB::q("INSERT INTO giao_dich_thanh_toan(ma_giao_dich_ref, hinh_thuc, so_tien, ID_hoa_don) VALUES (?, 'vnpay', ?, ?)",
            [$ref, $h['tong_tien'], $invoiceId]);
        $c = cfg('vnpay');
        $p = [
            'vnp_Version' => $c['version'], 'vnp_Command' => 'pay', 'vnp_TmnCode' => $c['tmn_code'] ?: 'MOONSDEMO',
            'vnp_Amount' => (int)round($h['tong_tien'] * 100), 'vnp_CurrCode' => 'VND', 'vnp_TxnRef' => $ref,
            'vnp_OrderInfo' => 'Thanh toan hoa don ' . $h['ma_hoa_don'], 'vnp_OrderType' => 'billpayment',
            'vnp_Locale' => 'vn', 'vnp_ReturnUrl' => $returnUrl, 'vnp_IpAddr' => client_ip(),
            'vnp_CreateDate' => date('YmdHis'), 'vnp_ExpireDate' => date('YmdHis', time() + 900),
        ];
        $p['vnp_SecureHash'] = self::sign($p);
        AuditService::log('tao_giao_dich_vnpay', 'giao_dich_thanh_toan', $ref, money($h['tong_tien']));
        return self::mockMode() ? url('vnpay/sandbox', $p) : $c['pay_url'] . '?' . http_build_query($p);
    }

    /** Xử lý tín hiệu ngầm IPN. Trả về mã phản hồi theo chuẩn VNPay */
    public static function ipn(array $q): array
    {
        $ref = $q['vnp_TxnRef'] ?? '';
        $gd = $ref ? DB::one("SELECT * FROM giao_dich_thanh_toan WHERE ma_giao_dich_ref=?", [$ref]) : null;
        if (!hash_equals(self::sign($q), (string)($q['vnp_SecureHash'] ?? ''))) {
            if ($gd && $gd['trang_thai'] === 'khoi_tao') {
                DB::q("UPDATE giao_dich_thanh_toan SET trang_thai='ngoai_le', ghi_chu='Sai chữ ký HMAC SHA512', chuoi_bam_secure_hash=?
                       WHERE ID_giao_dich=?", [substr((string)($q['vnp_SecureHash'] ?? ''), 0, 256), $gd['ID_giao_dich']]);
            }
            AuditService::log('ipn_sai_chu_ky', 'giao_dich_thanh_toan', $ref, null, null);
            return ['RspCode' => '97', 'Message' => 'Invalid signature'];
        }
        if (!$gd) return ['RspCode' => '01', 'Message' => 'Order not found'];
        if ((int)($q['vnp_Amount'] ?? 0) !== (int)round($gd['so_tien'] * 100)) {
            if ($gd['trang_thai'] === 'khoi_tao')
                DB::q("UPDATE giao_dich_thanh_toan SET trang_thai='ngoai_le', ghi_chu='Sai số tiền IPN' WHERE ID_giao_dich=?", [$gd['ID_giao_dich']]);
            return ['RspCode' => '04', 'Message' => 'Invalid amount'];
        }
        if ($gd['trang_thai'] !== 'khoi_tao') return ['RspCode' => '02', 'Message' => 'Order already confirmed'];

        $ok = ($q['vnp_ResponseCode'] ?? '') === '00' && ($q['vnp_TransactionStatus'] ?? '00') === '00';
        try {
            DB::tx(function () use ($gd, $q, $ok) {
                DB::q("UPDATE giao_dich_thanh_toan SET trang_thai=?, ma_ngan_hang=?, ma_gd_ngan_hang=?, ma_phan_hoi=?, chuoi_bam_secure_hash=?,
                       ngay_giao_dich=? WHERE ID_giao_dich=? AND trang_thai='khoi_tao'",
                    [$ok ? 'thanh_cong' : 'that_bai', $q['vnp_BankCode'] ?? null, $q['vnp_TransactionNo'] ?? null,
                     $q['vnp_ResponseCode'] ?? null, $q['vnp_SecureHash'], now(), $gd['ID_giao_dich']]);
                if ($ok) {
                    DB::q("UPDATE hoa_don SET trang_thai_thanh_toan='da_thanh_toan', ngay_thanh_toan=?, phuong_thuc_thanh_toan='vnpay'
                           WHERE ID_hoa_don=?", [now(), $gd['ID_hoa_don']]);
                }
            });
        } catch (PDOException $e) {
            // VD: hóa đơn đã được gạch nợ bằng tiền mặt trong lúc chờ VNPay => đưa vào danh sách ngoại lệ đối soát
            DB::q("UPDATE giao_dich_thanh_toan SET trang_thai='ngoai_le', ghi_chu=? WHERE ID_giao_dich=? AND trang_thai='khoi_tao'",
                [mb_substr(db_error_message($e), 0, 250), $gd['ID_giao_dich']]);
            AuditService::log('ipn_ngoai_le', 'giao_dich_thanh_toan', $gd['ma_giao_dich_ref'], db_error_message($e), null);
            return ['RspCode' => '02', 'Message' => 'Order already confirmed'];
        }
        AuditService::log($ok ? 'gach_no_tu_dong' : 'thanh_toan_that_bai', 'giao_dich_thanh_toan', $gd['ma_giao_dich_ref'],
            'ResponseCode ' . ($q['vnp_ResponseCode'] ?? ''), null);
        return ['RspCode' => '00', 'Message' => 'Confirm Success'];
    }

    /** Kế toán gạch nợ thủ công (tiền mặt/chuyển khoản tại quầy) – phải có số chứng từ, số tiền khớp hóa đơn */
    public static function manual(int $invoiceId, string $method, float $amount, string $payer, string $voucher): void
    {
        if (!Auth::is('ke_toan')) throw new BusinessException('Chỉ Kế toán được gạch nợ thủ công.');
        if (!in_array($method, ['tien_mat', 'chuyen_khoan'], true)) throw new BusinessException('Hình thức không hợp lệ.');
        if (mb_strlen($payer) < 3) throw new BusinessException('Vui lòng nhập người nộp tiền.');
        if (mb_strlen($voucher) < 3) throw new BusinessException('Vui lòng nhập số phiếu thu/biên lai để lưu vết.');
        DB::tx(function () use ($invoiceId, $method, $amount, $payer, $voucher) {
            $h = DB::one("SELECT * FROM hoa_don WHERE ID_hoa_don=? FOR UPDATE", [$invoiceId]);
            if (!$h || $h['trang_thai_thanh_toan'] !== 'chua_thanh_toan') throw new BusinessException('Hóa đơn không ở trạng thái chưa thanh toán.');
            if (abs($amount - (float)$h['tong_tien']) > 0.001)
                throw new BusinessException('Số tiền thu (' . money($amount) . ') phải bằng tổng tiền hóa đơn (' . money($h['tong_tien']) . ').');
            try {
                DB::q("INSERT INTO giao_dich_thanh_toan(ma_giao_dich_ref, hinh_thuc, so_tien, trang_thai, so_chung_tu, nguoi_nop,
                         ID_hoa_don, ID_nguoi_thuc_hien) VALUES (?,?,?,'thanh_cong',?,?,?,?)",
                    ['PT' . date('ymdHis') . random_int(1000, 9999), $method, $amount, $voucher, $payer, $invoiceId, Auth::id()]);
                DB::q("UPDATE hoa_don SET trang_thai_thanh_toan='da_thanh_toan', ngay_thanh_toan=?, phuong_thuc_thanh_toan=? WHERE ID_hoa_don=?",
                    [now(), $method, $invoiceId]);
            } catch (PDOException $e) { throw new BusinessException(db_error_message($e)); }
            AuditService::log('gach_no_thu_cong', 'hoa_don', $h['ma_hoa_don'], "$voucher - $payer - " . money($amount));
        });
    }
}
