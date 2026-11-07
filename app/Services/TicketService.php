<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\PhieuSuCo;
use App\Models\TaiKhoan;
use App\Support\Db;
use App\Support\Rbac;
use PDOException;

/* ======================= SỰ CỐ & KANBAN (UC-RES08, UC-DPV01, UC-TEC01/02) ======================= */
final class TicketService
{
    /** Chuyển trạng thái hợp lệ cho kỹ thuật viên trên bảng Kanban */
    const TECH_MOVES = ['da_phan_cong' => ['dang_xu_ly'], 'dang_xu_ly' => ['cho_linh_kien', 'cho_nghiem_thu'],
        'cho_linh_kien' => ['dang_xu_ly']];

    public static function create(array $me, array $d, string $photo): int
    {
        if (mb_strlen($d['tieu_de'] ?? '') < 5) throw new BusinessException('Tiêu đề sự cố tối thiểu 5 ký tự.');
        if (mb_strlen($d['mo_ta_chi_tiet'] ?? '') < 10) throw new BusinessException('Mô tả chi tiết tối thiểu 10 ký tự.');
        if (!isset(LABELS['pham_vi'][$d['pham_vi'] ?? '']) || !isset(LABELS['muc_do'][$d['muc_do_uu_tien'] ?? '']))
            throw new BusinessException('Vui lòng chọn phạm vi và mức độ.');
        $p = PhieuSuCo::create(['ma_phieu' => 'TMP' . bin2hex(random_bytes(6)), 'tieu_de' => $d['tieu_de'], 'mo_ta_chi_tiet' => $d['mo_ta_chi_tiet'],
            'pham_vi' => $d['pham_vi'], 'vi_tri_su_co' => ($d['vi_tri_su_co'] ?? '') ?: null, 'muc_do_uu_tien' => $d['muc_do_uu_tien'],
            'anh_hien_truong' => $photo, 'ID_can_ho' => $me['ID_can_ho'], 'ID_nguoi_gui' => $me['ID_nhan_khau']]);
        $id = $p->ID_phieu;
        $p->update(['ma_phieu' => 'TK-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT)]);
        AuditService::log('tao_phieu_su_co', 'phieu_su_co', $id, $d['tieu_de']);
        return $id;
    }
    private static function get(int $id): array
    {
        $t = PhieuSuCo::find($id);
        if (!$t) throw new BusinessException('Không tìm thấy phiếu sự cố.');
        return $t->getAttributes();
    }
    public static function assign(int $id, int $tech): void
    {
        if (!Rbac::is('dieu_phoi_vien')) throw new BusinessException('Chỉ Điều phối viên được phân công phiếu.');
        $t = self::get($id);
        if ($t['trang_thai_kanban'] !== 'cho_tiep_nhan') throw new BusinessException('Phiếu không ở trạng thái chờ tiếp nhận.');
        if (!TaiKhoan::whereKey($tech)->where('vai_tro', 'ky_thuat_vien')->where('trang_thai', 1)->exists())
            throw new BusinessException('Kỹ thuật viên không hợp lệ.');
        Db::q("UPDATE phieu_su_co SET trang_thai_kanban='da_phan_cong', ID_ky_thuat_vien=?, ID_nguoi_phan_cong=?, ngay_phan_cong=? WHERE ID_phieu=?",
            [$tech, Rbac::id(), app_now(), $id]);
        AuditService::log('phan_cong_su_co', 'phieu_su_co', $id, "KTV #$tech");
    }
    public static function reject(int $id, string $reason): void
    {
        if (!Rbac::is('dieu_phoi_vien')) throw new BusinessException('Chỉ Điều phối viên được từ chối phiếu.');
        if (mb_strlen($reason) < 5) throw new BusinessException('Vui lòng nhập lý do từ chối.');
        if (self::get($id)['trang_thai_kanban'] !== 'cho_tiep_nhan') throw new BusinessException('Phiếu không ở trạng thái chờ tiếp nhận.');
        Db::q("UPDATE phieu_su_co SET trang_thai_kanban='tu_choi', ly_do_tu_choi=? WHERE ID_phieu=?", [$reason, $id]);
        AuditService::log('tu_choi_su_co', 'phieu_su_co', $id, $reason);
    }
    /** Kỹ thuật viên kéo thả thẻ – chỉ trên phiếu được giao cho chính mình */
    public static function move(int $id, string $to, string $note = ''): void
    {
        $t = self::get($id);
        if (!Rbac::is('ky_thuat_vien') || (int)$t['ID_ky_thuat_vien'] !== Rbac::id())
            throw new BusinessException('Bạn chỉ được cập nhật phiếu được phân công cho chính mình.');
        if (!in_array($to, self::TECH_MOVES[$t['trang_thai_kanban']] ?? [], true))
            throw new BusinessException('Không thể chuyển từ "' . lbl('kanban', $t['trang_thai_kanban']) . '" sang "' . lbl('kanban', $to) . '".');
        if ($to === 'cho_nghiem_thu') throw new BusinessException('Vui lòng dùng khung nghiệm thu (bắt buộc ảnh kết quả).');
        if ($to === 'cho_linh_kien' && mb_strlen($note) < 3) throw new BusinessException('Vui lòng ghi tên vật tư cần chờ.');
        Db::q("UPDATE phieu_su_co SET trang_thai_kanban=?, ghi_chu_ky_thuat=COALESCE(?, ghi_chu_ky_thuat) WHERE ID_phieu=?",
            [$to, $note ?: null, $id]);
        AuditService::log('cap_nhat_su_co', 'phieu_su_co', $id, $to);
    }
    /** Kỹ thuật viên đề xuất nghiệm thu: bắt buộc ≥1 ảnh kết quả (CHECK ck_psc_anh ở CSDL) */
    public static function submitAcceptance(int $id, ?string $photo, string $materials, string $note): void
    {
        $t = self::get($id);
        if (!Rbac::is('ky_thuat_vien') || (int)$t['ID_ky_thuat_vien'] !== Rbac::id())
            throw new BusinessException('Bạn chỉ được cập nhật phiếu được phân công cho chính mình.');
        if ($t['trang_thai_kanban'] !== 'dang_xu_ly') throw new BusinessException('Chỉ đề xuất nghiệm thu khi phiếu đang xử lý.');
        if (mb_strlen($note) < 5) throw new BusinessException('Vui lòng nhập ghi chú kỹ thuật.');
        try {
            Db::q("UPDATE phieu_su_co SET trang_thai_kanban='cho_nghiem_thu', anh_nghiem_thu=?, vat_tu_tieu_hao=?, ghi_chu_ky_thuat=? WHERE ID_phieu=?",
                [$photo, $materials ?: null, $note, $id]);
        } catch (PDOException $e) { throw new BusinessException(db_error_message($e)); }
        AuditService::log('de_xuat_nghiem_thu', 'phieu_su_co', $id);
    }
    public static function confirm(array $me, int $id, int $stars): void
    {
        $t = self::get($id);
        if ((int)$t['ID_can_ho'] !== (int)$me['ID_can_ho'] || $t['trang_thai_kanban'] !== 'cho_nghiem_thu')
            throw new BusinessException('Phiếu không ở trạng thái chờ nghiệm thu.');
        if ($stars < 1 || $stars > 5) throw new BusinessException('Vui lòng chấm điểm từ 1 đến 5 sao.');
        Db::q("UPDATE phieu_su_co SET trang_thai_kanban='hoan_tat', diem_danh_gia=?, ngay_hoan_thanh=? WHERE ID_phieu=?", [$stars, app_now(), $id]);
        AuditService::log('nghiem_thu_su_co', 'phieu_su_co', $id, "$stars sao");
    }
    public static function reopen(array $me, int $id, string $reason): void
    {
        $t = self::get($id);
        if ((int)$t['ID_can_ho'] !== (int)$me['ID_can_ho'] || $t['trang_thai_kanban'] !== 'cho_nghiem_thu')
            throw new BusinessException('Phiếu không ở trạng thái chờ nghiệm thu.');
        if (mb_strlen($reason) < 5) throw new BusinessException('Vui lòng mô tả điểm chưa đạt.');
        Db::q("UPDATE phieu_su_co SET trang_thai_kanban='dang_xu_ly', so_lan_mo_lai=so_lan_mo_lai+1,
               ghi_chu_ky_thuat=CONCAT('[Cư dân yêu cầu sửa lại] ', ?) WHERE ID_phieu=?", [$reason, $id]);
        AuditService::log('mo_lai_su_co', 'phieu_su_co', $id, $reason);
    }
    public static function cancel(array $me, int $id): void
    {
        $n = Db::q("UPDATE phieu_su_co SET trang_thai_kanban='da_huy' WHERE ID_phieu=? AND ID_can_ho=? AND trang_thai_kanban='cho_tiep_nhan'",
            [$id, $me['ID_can_ho']])->rowCount();
        if (!$n) throw new BusinessException('Chỉ hủy được phiếu đang chờ tiếp nhận.');
        AuditService::log('huy_su_co', 'phieu_su_co', $id);
    }
    /** Phiếu khẩn cấp chưa phân công quá 15 phút (vi phạm SLA) */
    public static function slaBreached(array $t): bool
    {
        return $t['muc_do_uu_tien'] === 'khan_cap' && $t['trang_thai_kanban'] === 'cho_tiep_nhan'
            && strtotime(app_now()) - strtotime($t['ngay_tao']) > 15 * 60;
    }
}
