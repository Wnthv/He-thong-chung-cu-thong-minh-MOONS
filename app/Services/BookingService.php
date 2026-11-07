<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\DichVuTienIch;
use App\Support\Db;
use App\Support\Rbac;

/* ======================= ĐẶT TIỆN ÍCH (UC-RES07, UC-REC05) ======================= */
final class BookingService
{
    const CANCEL_HOURS = 2;
    /** Dùng trong kiểm thử đồng thời: giữ khóa thêm N micro-giây trước khi ghi */
    public static int $testDelayUs = 0;

    public static function facilities(): array
    {
        return DichVuTienIch::orderBy('ID_tien_ich')->get()->map->getAttributes()->all();
    }
    public static function slotList(array $f): array
    {
        $r = [];
        for ($t = strtotime($f['gio_mo_cua']); $t + $f['thoi_luong_khung'] * 60 <= strtotime($f['gio_dong_cua']); $t += $f['thoi_luong_khung'] * 60) {
            $r[] = [date('H:i:s', $t), date('H:i:s', $t + $f['thoi_luong_khung'] * 60)];
        }
        return $r;
    }
    /** Trạng thái từng khung giờ trong ngày của 1 tiện ích */
    public static function slots(int $facilityId, string $date): array
    {
        $f = Db::one("SELECT * FROM dich_vu_tien_ich WHERE ID_tien_ich=?", [$facilityId]);
        $bk = Db::all("SELECT d.*, ch.ma_can_ho, nk.ho_ten FROM dat_tien_ich d LEFT JOIN can_ho ch ON ch.ID_can_ho=d.ID_can_ho
                         LEFT JOIN nhan_khau nk ON nk.ID_nhan_khau=d.ID_nhan_khau
                        WHERE d.ID_tien_ich=? AND d.ngay_dat=? AND d.trang_thai_dat IN ('da_xac_nhan','da_su_dung')", [$facilityId, $date]);
        $out = [];
        foreach (self::slotList($f) as [$s, $e]) {
            $people = 0; $state = 'trong'; $who = null;
            foreach ($bk as $b) {
                if ($b['khung_gio_bat_dau'] < $e && $b['khung_gio_ket_thuc'] > $s) {
                    if ($b['loai'] === 'bao_tri') { $state = 'bao_tri'; break; }
                    $people += (int)$b['so_nguoi'];
                    $who = $b;
                    if ($f['doc_quyen']) $state = 'da_dat';
                }
            }
            if ($state === 'trong' && !$f['doc_quyen'] && $people >= $f['suc_chua_toi_da']) $state = 'het_cho';
            $past = $date < app_today() || ($date === app_today() && $s <= date('H:i:s'));
            $out[] = ['start' => $s, 'end' => $e, 'state' => $past && $state === 'trong' ? 'qua_gio' : $state,
                'people' => $people, 'booking' => $who];
        }
        return $out;
    }

    public static function book(array $me, int $facilityId, string $date, string $start, int $people): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new BusinessException('Ngày sử dụng không hợp lệ.');
        if ($date < app_today()) throw new BusinessException('Không được chọn ngày trong quá khứ.');
        if ($date > date('Y-m-d', strtotime(app_today() . ' +30 days'))) throw new BusinessException('Chỉ được đặt trước tối đa 30 ngày.');
        if ($people < 1) throw new BusinessException('Số người tham gia phải lớn hơn 0.');
        return Db::tx(function () use ($me, $facilityId, $date, $start, $people) {
            // Khóa dòng tiện ích: mọi giao dịch đặt cùng tiện ích được xử lý tuần tự (chống trùng lịch khi đặt đồng thời)
            $f = Db::one("SELECT * FROM dich_vu_tien_ich WHERE ID_tien_ich=? FOR UPDATE", [$facilityId]);
            if (!$f || $f['trang_thai_hoat_dong'] !== 'hoat_dong') throw new BusinessException('Tiện ích đang tạm dừng hoạt động.');
            $slot = null;
            foreach (self::slotList($f) as $s) if (substr($s[0], 0, 5) === substr($start, 0, 5)) $slot = $s;
            if (!$slot) throw new BusinessException('Khung giờ không hợp lệ.');
            if ($date === app_today() && $slot[0] <= date('H:i:s')) throw new BusinessException('Khung giờ này đã qua.');
            if ($people > $f['suc_chua_toi_da']) throw new BusinessException("Vượt quá sức chứa cho phép ({$f['suc_chua_toi_da']} người).");
            if ($f['doc_quyen'] && BillingService::overdue30((int)$me['ID_can_ho']))
                throw new BusinessException('Căn hộ có hóa đơn quá hạn trên 30 ngày. Vui lòng thanh toán để mở lại quyền đặt tiện ích.');
            if ($f['han_muc_tuan']) {
                $used = (int)Db::val("SELECT COUNT(*) FROM dat_tien_ich WHERE ID_can_ho=? AND ID_tien_ich=? AND loai='dat_cho'
                        AND trang_thai_dat IN ('da_xac_nhan','da_su_dung','huy_muon') AND YEARWEEK(ngay_dat,3)=YEARWEEK(?,3)",
                    [$me['ID_can_ho'], $facilityId, $date]);
                if ($used >= $f['han_muc_tuan'])
                    throw new BusinessException("Căn hộ đã dùng hết hạn mức {$f['han_muc_tuan']} lượt {$f['ten_tien_ich']} trong tuần này.");
            }
            $overlap = Db::all("SELECT loai, so_nguoi FROM dat_tien_ich WHERE ID_tien_ich=? AND ngay_dat=?
                     AND trang_thai_dat IN ('da_xac_nhan','da_su_dung') AND khung_gio_bat_dau < ? AND khung_gio_ket_thuc > ?",
                [$facilityId, $date, $slot[1], $slot[0]]);
            foreach ($overlap as $o) if ($o['loai'] === 'bao_tri') throw new BusinessException('Khung giờ này đang khóa để bảo trì.');
            if ($f['doc_quyen'] && $overlap)
                throw new BusinessException('Khung giờ này vừa được đăng ký bởi cư dân khác, vui lòng chọn khung giờ khác.');
            if (!$f['doc_quyen'] && array_sum(array_column($overlap, 'so_nguoi')) + $people > $f['suc_chua_toi_da'])
                throw new BusinessException('Khung giờ này không còn đủ chỗ.');
            if (self::$testDelayUs) usleep(self::$testDelayUs);
            $code = sprintf('MOONS-%s-%s-%s-%s-%s', $f['ma_tien_ich'], str_replace('-', '', $me['ma_can_ho']),
                str_replace('-', '', $date), substr($slot[0], 0, 2), strtoupper(bin2hex(random_bytes(2))));
            Db::q("INSERT INTO dat_tien_ich(ma_ve, ngay_dat, khung_gio_bat_dau, khung_gio_ket_thuc, so_nguoi, ID_tien_ich, ID_nhan_khau, ID_can_ho, ID_nguoi_tao)
                   VALUES (?,?,?,?,?,?,?,?,?)", [$code, $date, $slot[0], $slot[1], $people, $facilityId, $me['ID_nhan_khau'], $me['ID_can_ho'],
                $me['ID_tai_khoan'] ?? Rbac::id()]);
            $id = Db::id();
            AuditService::log('dat_tien_ich', 'dat_tien_ich', $id, $code);
            return ['id' => $id, 'code' => $code];
        });
    }

    public static function cancel(array $me, int $id): string
    {
        $b = Db::one("SELECT * FROM dat_tien_ich WHERE ID_dat_lich=? AND ID_can_ho=? AND loai='dat_cho'", [$id, $me['ID_can_ho']]);
        if (!$b || $b['trang_thai_dat'] !== 'da_xac_nhan') throw new BusinessException('Không thể hủy lịch này.');
        $startTs = strtotime("{$b['ngay_dat']} {$b['khung_gio_bat_dau']}");
        $late = $startTs - strtotime(app_now()) < self::CANCEL_HOURS * 3600;
        Db::q("UPDATE dat_tien_ich SET trang_thai_dat=?, ngay_huy=? WHERE ID_dat_lich=?", [$late ? 'huy_muon' : 'da_huy', app_now(), $id]);
        AuditService::log($late ? 'huy_muon_tien_ich' : 'huy_tien_ich', 'dat_tien_ich', $id);
        return $late ? 'Đã hủy. Do hủy trong vòng 2 giờ trước giờ sử dụng, lượt này vẫn được tính vào hạn mức tuần.'
            : 'Đã hủy lịch và mở lại khung giờ cho cư dân khác.';
    }

    /** Lễ tân xác thực vé QR khi cư dân đến sử dụng */
    public static function verify(string $code): array
    {
        $b = Db::one("SELECT d.*, t.ten_tien_ich, ch.ma_can_ho, nk.ho_ten FROM dat_tien_ich d JOIN dich_vu_tien_ich t ON t.ID_tien_ich=d.ID_tien_ich
                       LEFT JOIN can_ho ch ON ch.ID_can_ho=d.ID_can_ho LEFT JOIN nhan_khau nk ON nk.ID_nhan_khau=d.ID_nhan_khau
                      WHERE d.ma_ve=? AND d.loai='dat_cho'", [strtoupper(trim($code))]);
        if (!$b) throw new BusinessException('Mã vé không tồn tại.');
        if ($b['trang_thai_dat'] !== 'da_xac_nhan') throw new BusinessException('Vé ở trạng thái "' . lbl('ve', $b['trang_thai_dat']) . '", không hợp lệ.');
        if ($b['ngay_dat'] !== app_today()) throw new BusinessException('Vé dùng cho ngày ' . vn_date($b['ngay_dat']) . ', không phải hôm nay.');
        Db::q("UPDATE dat_tien_ich SET trang_thai_dat='da_su_dung' WHERE ID_dat_lich=?", [$b['ID_dat_lich']]);
        AuditService::log('xac_thuc_ve', 'dat_tien_ich', $b['ID_dat_lich'], $b['ma_ve']);
        return $b;
    }

    /** Khóa lịch bảo trì: tự động hủy các lịch đã đặt trùng khoảng thời gian */
    public static function maintenance(int $facilityId, string $date, string $start, string $end, string $note = ''): int
    {
        if (!Rbac::is('ke_toan_truong', 'admin')) throw new BusinessException('Chỉ Ban Quản lý/Admin được khóa lịch bảo trì.');
        if ($date < app_today() || $end <= $start) throw new BusinessException('Khoảng thời gian bảo trì không hợp lệ.');
        return Db::tx(function () use ($facilityId, $date, $start, $end) {
            Db::q("SELECT ID_tien_ich FROM dich_vu_tien_ich WHERE ID_tien_ich=? FOR UPDATE", [$facilityId]);
            $n = Db::q("UPDATE dat_tien_ich SET trang_thai_dat='da_huy', ngay_huy=? WHERE ID_tien_ich=? AND ngay_dat=? AND loai='dat_cho'
                        AND trang_thai_dat='da_xac_nhan' AND khung_gio_bat_dau < ? AND khung_gio_ket_thuc > ?",
                [app_now(), $facilityId, $date, $end, $start])->rowCount();
            Db::q("INSERT INTO dat_tien_ich(ma_ve, loai, ngay_dat, khung_gio_bat_dau, khung_gio_ket_thuc, ID_tien_ich, ID_nguoi_tao)
                   VALUES (?, 'bao_tri', ?, ?, ?, ?, ?)", ['BT-' . date('ymdHis') . random_int(10, 99), $date, $start, $end, $facilityId, Rbac::id()]);
            AuditService::log('khoa_bao_tri', 'dich_vu_tien_ich', $facilityId, "$date $start-$end, hủy $n lịch");
            return $n;
        });
    }
}
