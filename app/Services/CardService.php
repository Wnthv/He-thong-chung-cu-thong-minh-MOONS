<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CanHo;
use App\Models\TheRaVao;
use App\Support\Db;
use App\Support\Rbac;
use PDOException;

/* ======================= THẺ TỪ & VÂN TAY (UC-RES03, UC-REC02) ======================= */
final class CardService
{
    public static function issue(string $maCanHo, string $loai, string $rfid, string $vanTay, ?int $nkId = null): int
    {
        $ch = CanHo::where('ma_can_ho', strtoupper($maCanHo))->first();
        if (!$ch) throw new BusinessException('Không tìm thấy mã căn hộ.');
        $nk = $nkId ? Db::one("SELECT * FROM nhan_khau WHERE ID_nhan_khau=? AND ID_can_ho=? AND trang_thai_duyet='da_duyet'", [$nkId, $ch['ID_can_ho']])
            : Db::one("SELECT * FROM nhan_khau WHERE ID_can_ho=? AND trang_thai_duyet='da_duyet' AND ID_tai_khoan IS NOT NULL
                        ORDER BY loai_cu_dan='khach_thue' DESC LIMIT 1", [$ch['ID_can_ho']]);
        if (!$nk) throw new BusinessException('Căn hộ chưa có cư dân đã duyệt để cấp thẻ.');
        if (!isset(LABELS['loai_the'][$loai])) throw new BusinessException('Loại thẻ không hợp lệ.');
        $rfid = strtoupper(trim($rfid)) ?: null;
        $vanTay = strtoupper(trim($vanTay)) ?: null;
        if ($loai === 'van_tay' && !$vanTay) throw new BusinessException('Vui lòng nạp mã ID vân tay từ thiết bị đọc.');
        if ($loai !== 'van_tay' && !$rfid) throw new BusinessException('Vui lòng quẹt thẻ để nạp mã chip RFID.');
        if ($loai === 'van_tay') $rfid = null; else $vanTay = null;
        try {
            $id = TheRaVao::create(['ma_chip_rfid' => $rfid, 'ma_id_van_tay' => $vanTay, 'loai_the' => $loai,
                'ID_nhan_khau' => $nk['ID_nhan_khau']])->ID_the;
        } catch (PDOException $e) { throw new BusinessException(db_error_message($e)); }
        AuditService::log('cap_the', 'the_ra_vao', $id, "{$ch['ma_can_ho']} - " . ($rfid ?? $vanTay));
        return $id;
    }
    public static function setState(int $id, string $state, ?int $canHoOwner = null): void
    {
        $c = Db::one("SELECT t.*, nk.ID_can_ho FROM the_ra_vao t JOIN nhan_khau nk ON nk.ID_nhan_khau=t.ID_nhan_khau WHERE ID_the=?", [$id]);
        if (!$c || ($canHoOwner && (int)$c['ID_can_ho'] !== $canHoOwner)) throw new BusinessException('Không tìm thấy thẻ.');
        if ($c['trang_thai_the'] === 'da_huy') throw new BusinessException('Thẻ đã hủy.');
        Db::q("UPDATE the_ra_vao SET trang_thai_the=? WHERE ID_the=?", [$state, $id]);
        AuditService::log('cap_nhat_the', 'the_ra_vao', $id, $state);
    }
}
