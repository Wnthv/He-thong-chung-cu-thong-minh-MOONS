<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Support\Db;
use App\Support\Rbac;
use PDOException;

/* ======================= THẺ XE (UC-RES04, UC-REC03) ======================= */
final class VehicleService
{
    const LIMIT = ['o_to' => 2, 'xe_may' => 4];

    public static function normalizePlate(string $p): string
    {
        return strtoupper(preg_replace('/\s+/', '', $p));
    }
    /** Số xe đang chiếm định mức (chờ duyệt + yêu cầu bổ sung + đã duyệt) */
    public static function used(int $canHo, string $type, ?int $exclude = null, bool $approvedOnly = false): int
    {
        $st = $approvedOnly ? "('da_duyet')" : "('cho_duyet','yeu_cau_bo_sung','da_duyet')";
        return (int)Db::val("SELECT COUNT(*) FROM the_xe WHERE ID_can_ho=? AND loai_xe=? AND trang_thai_duyet IN $st
                             AND ID_the_xe <> ?", [$canHo, $type, $exclude ?? 0]);
    }
    public static function register(array $me, array $d, ?string $photo): int
    {
        $type = $d['loai_xe'] ?? '';
        if (!isset(self::LIMIT[$type])) throw new BusinessException('Vui lòng chọn loại xe.');
        $plate = self::normalizePlate($d['bien_so_xe'] ?? '');
        if (!preg_match('/^\d{2}[A-Z]{1,2}\d?-(\d{3}\.?\d{2}|\d{4})$/', $plate))
            throw new BusinessException('Biển số không đúng định dạng biển số xe Việt Nam (VD: 51G-102.24, 59X3-343.41).');
        if (mb_strlen($d['nhan_hieu'] ?? '') < 2 || mb_strlen($d['mau_xe'] ?? '') < 2) throw new BusinessException('Vui lòng nhập nhãn hiệu và màu xe.');
        if (!$photo) throw new BusinessException('Vui lòng đính kèm ảnh cà vẹt xe.');
        return Db::tx(function () use ($me, $d, $type, $plate, $photo) {
            Db::q("SELECT ID_can_ho FROM can_ho WHERE ID_can_ho=? FOR UPDATE", [$me['ID_can_ho']]);   // tuần tự hóa theo căn hộ
            if (self::used((int)$me['ID_can_ho'], $type) >= self::LIMIT[$type])
                throw new BusinessException('Căn hộ đã đạt định mức tối đa ' . self::LIMIT[$type] . ' ' . lbl('loai_xe', $type) . '.');
            try {
                Db::q("INSERT INTO the_xe(bien_so_xe, loai_xe, nhan_hieu, mau_xe, anh_ca_vet_xe, ID_can_ho, ID_nhan_khau)
                       VALUES (?,?,?,?,?,?,?)", [$plate, $type, $d['nhan_hieu'], $d['mau_xe'], $photo, $me['ID_can_ho'], $me['ID_nhan_khau']]);
            } catch (PDOException $e) { throw new BusinessException(db_error_message($e)); }
            $id = Db::id();
            AuditService::log('dang_ky_the_xe', 'the_xe', $id, $plate);
            return $id;
        });
    }
    /** Cư dân bổ sung hồ sơ khi Lễ tân yêu cầu (giữ nguyên bản ghi để lưu lịch sử) */
    public static function resubmit(array $me, int $id, ?string $photo): void
    {
        $x = Db::one("SELECT * FROM the_xe WHERE ID_the_xe=? AND ID_can_ho=?", [$id, $me['ID_can_ho']]);
        if (!$x || $x['trang_thai_duyet'] !== 'yeu_cau_bo_sung') throw new BusinessException('Hồ sơ không ở trạng thái yêu cầu bổ sung.');
        Db::q("UPDATE the_xe SET trang_thai_duyet='cho_duyet', anh_ca_vet_xe=COALESCE(?, anh_ca_vet_xe), ngay_gui=? WHERE ID_the_xe=?",
            [$photo, app_now(), $id]);
        AuditService::log('bo_sung_the_xe', 'the_xe', $id);
    }
    public static function approve(int $id): void
    {
        Db::tx(function () use ($id) {
            $x = Db::one("SELECT * FROM the_xe WHERE ID_the_xe=?", [$id]);
            if (!$x || $x['trang_thai_duyet'] !== 'cho_duyet') throw new BusinessException('Hồ sơ không ở trạng thái chờ duyệt.');
            Db::q("SELECT ID_can_ho FROM can_ho WHERE ID_can_ho=? FOR UPDATE", [$x['ID_can_ho']]);
            if (self::used((int)$x['ID_can_ho'], $x['loai_xe'], $id, true) >= self::LIMIT[$x['loai_xe']])
                throw new BusinessException('Căn hộ này đã đạt định mức tối đa ' . self::LIMIT[$x['loai_xe']] . ' ' . lbl('loai_xe', $x['loai_xe']) . '.');
            Db::q("UPDATE the_xe SET trang_thai_duyet='da_duyet', ngay_duyet=?, ID_nguoi_duyet=?, ly_do=NULL WHERE ID_the_xe=?",
                [app_now(), Rbac::id(), $id]);
            AuditService::log('duyet_the_xe', 'the_xe', $id, $x['bien_so_xe']);
        });
    }
    public static function decline(int $id, string $state, string $reason): void
    {
        if (!in_array($state, ['tu_choi', 'yeu_cau_bo_sung'], true)) throw new BusinessException('Thao tác không hợp lệ.');
        if (mb_strlen($reason) < 5) throw new BusinessException('Vui lòng nhập lý do.');
        $n = Db::q("UPDATE the_xe SET trang_thai_duyet=?, ly_do=?, ngay_duyet=?, ID_nguoi_duyet=? WHERE ID_the_xe=? AND trang_thai_duyet='cho_duyet'",
            [$state, $reason, app_now(), Rbac::id(), $id])->rowCount();
        if (!$n) throw new BusinessException('Hồ sơ không ở trạng thái chờ duyệt.');
        AuditService::log($state === 'tu_choi' ? 'tu_choi_the_xe' : 'yeu_cau_bo_sung', 'the_xe', $id, $reason);
    }
    public static function cancel(array $me, int $id): void
    {
        $n = Db::q("UPDATE the_xe SET trang_thai_duyet='da_huy', ly_do='Cư dân hủy thẻ' WHERE ID_the_xe=? AND ID_can_ho=?
                    AND trang_thai_duyet IN ('cho_duyet','yeu_cau_bo_sung','da_duyet')", [$id, $me['ID_can_ho']])->rowCount();
        if (!$n) throw new BusinessException('Không thể hủy thẻ xe này.');
        AuditService::log('huy_the_xe', 'the_xe', $id);
    }
}
