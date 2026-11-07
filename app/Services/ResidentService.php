<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Support\Db;
use App\Support\Rbac;
use PDOException;

/* ======================= CĂN HỘ & NHÂN KHẨU (UC-RES01/02, UC-REC01) ======================= */
final class ResidentService
{
    /** Đồng bộ trạng thái ở của căn hộ theo nhân khẩu đã duyệt (dữ liệu dẫn xuất) */
    public static function syncOccupancy(int $canHo): string
    {
        $t = app_today();
        $tenant = Db::val("SELECT COUNT(*) FROM nhan_khau WHERE ID_can_ho=? AND loai_cu_dan='khach_thue'
                AND trang_thai_duyet='da_duyet' AND ? BETWEEN ngay_bat_dau_hop_dong AND ngay_ket_thuc_hop_dong", [$canHo, $t]);
        $owner = Db::val("SELECT COUNT(*) FROM nhan_khau WHERE ID_can_ho=? AND loai_cu_dan='chu_ho' AND trang_thai_duyet='da_duyet'", [$canHo]);
        $state = $tenant ? 'cho_thue' : ($owner ? 'chu_ho_o' : 'trong');
        Db::q("UPDATE can_ho SET trang_thai_o = ? WHERE ID_can_ho = ?", [$state, $canHo]);
        return $state;
    }

    /** Khai báo thành viên (Chủ hộ/Khách thuê) hoặc khai báo Khách thuê (chỉ Chủ hộ) */
    public static function declare(array $me, array $d, ?string $photo): int
    {
        $type = $d['loai_cu_dan'] ?? 'thanh_vien';
        if (!in_array($type, ['thanh_vien', 'khach_thue'], true)) throw new BusinessException('Loại cư dân không hợp lệ.');
        if ($type === 'khach_thue' && $me['loai_cu_dan'] !== 'chu_ho') throw new BusinessException('Chỉ Chủ hộ được khai báo Khách thuê.');
        if (mb_strlen($d['ho_ten'] ?? '') < 3) throw new BusinessException('Vui lòng nhập họ và tên.');
        if (empty($d['ngay_sinh']) || $d['ngay_sinh'] > app_today()) throw new BusinessException('Ngày sinh không hợp lệ.');
        if (empty($d['quan_he_chu_ho'])) throw new BusinessException('Vui lòng chọn quan hệ với chủ hộ.');
        $cccd = $d['so_cccd'] ?: null;
        $age = (int)date_diff(date_create($d['ngay_sinh']), date_create(app_today()))->y;
        if ($age >= 14 && !$cccd) throw new BusinessException('Người từ 14 tuổi trở lên bắt buộc khai báo số CCCD.');
        if ($cccd && !$photo) throw new BusinessException('Vui lòng đính kèm ảnh chụp CCCD.');
        if ($type === 'khach_thue') {
            if (!preg_match('/^0\d{9}$/', $d['so_dien_thoai'] ?? '')) throw new BusinessException('Khách thuê phải có số điện thoại hợp lệ để cấp tài khoản.');
            if (empty($d['ngay_bat_dau_hop_dong']) || empty($d['ngay_ket_thuc_hop_dong']) || $d['ngay_ket_thuc_hop_dong'] < $d['ngay_bat_dau_hop_dong'])
                throw new BusinessException('Thời hạn hợp đồng thuê không hợp lệ.');
            if (!$photo) throw new BusinessException('Vui lòng đính kèm ảnh hợp đồng thuê.');
        }
        try {
            Db::q("INSERT INTO nhan_khau(ho_ten, ngay_sinh, gioi_tinh, so_dien_thoai, email, so_cccd, quan_he_chu_ho, loai_cu_dan,
                     so_hop_dong, ngay_bat_dau_hop_dong, ngay_ket_thuc_hop_dong, anh_giay_to, ID_can_ho)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$d['ho_ten'], $d['ngay_sinh'], $d['gioi_tinh'] ?: null, $d['so_dien_thoai'] ?: null, $d['email'] ?: null, $cccd,
                 $type === 'khach_thue' ? 'Khách thuê' : $d['quan_he_chu_ho'], $type,
                 $type === 'khach_thue' ? ($d['so_hop_dong'] ?: null) : null,
                 $type === 'khach_thue' ? $d['ngay_bat_dau_hop_dong'] : null,
                 $type === 'khach_thue' ? $d['ngay_ket_thuc_hop_dong'] : null, $photo, $me['ID_can_ho']]);
        } catch (PDOException $e) { throw new BusinessException(db_error_message($e)); }
        $id = Db::id();
        AuditService::log('khai_bao_nhan_khau', 'nhan_khau', $id, $d['ho_ten']);
        return $id;
    }

    /** Lễ tân duyệt hồ sơ. Khách thuê được cấp tài khoản (mật khẩu tạm, bắt buộc đổi) */
    public static function approve(int $id): ?array
    {
        return Db::tx(function () use ($id) {
            $nk = Db::one("SELECT * FROM nhan_khau WHERE ID_nhan_khau = ? FOR UPDATE", [$id]);
            if (!$nk || $nk['trang_thai_duyet'] !== 'cho_duyet') throw new BusinessException('Hồ sơ không ở trạng thái chờ duyệt.');
            $acc = null;
            if ($nk['loai_cu_dan'] === 'khach_thue') {
                $pw = AccountService::tempPassword();
                try {
                    Db::q("INSERT INTO tai_khoan(ten_dang_nhap, mat_khau_bam, ho_ten, email, so_dien_thoai, vai_tro, phai_doi_mat_khau)
                           VALUES (?,?,?,?,?,'khach_thue',1)",
                        [$nk['so_dien_thoai'], password_hash($pw, PASSWORD_BCRYPT), $nk['ho_ten'], $nk['email'], $nk['so_dien_thoai']]);
                } catch (PDOException $e) { throw new BusinessException(db_error_message($e)); }
                $uid = Db::id();
                Db::q("UPDATE nhan_khau SET ID_tai_khoan = ? WHERE ID_nhan_khau = ?", [$uid, $id]);
                $acc = ['username' => $nk['so_dien_thoai'], 'password' => $pw];
            }
            Db::q("UPDATE nhan_khau SET trang_thai_duyet='da_duyet', ngay_duyet=?, ID_nguoi_duyet=? WHERE ID_nhan_khau=?",
                [app_now(), Rbac::id(), $id]);
            self::syncOccupancy((int)$nk['ID_can_ho']);
            AuditService::log('duyet_nhan_khau', 'nhan_khau', $id, $nk['ho_ten']);
            return $acc;
        });
    }
    public static function reject(int $id, string $reason): void
    {
        if (mb_strlen($reason) < 5) throw new BusinessException('Vui lòng nhập lý do từ chối.');
        $n = Db::q("UPDATE nhan_khau SET trang_thai_duyet='tu_choi', ly_do_tu_choi=?, ngay_duyet=?, ID_nguoi_duyet=?
                    WHERE ID_nhan_khau=? AND trang_thai_duyet='cho_duyet'", [$reason, app_now(), Rbac::id(), $id])->rowCount();
        if (!$n) throw new BusinessException('Hồ sơ không ở trạng thái chờ duyệt.');
        AuditService::log('tu_choi_nhan_khau', 'nhan_khau', $id, $reason);
    }
    /** Chủ hộ báo thành viên chuyển đi (không xóa cứng để giữ lịch sử) */
    public static function remove(array $me, int $id): void
    {
        if ($me['loai_cu_dan'] !== 'chu_ho') throw new BusinessException('Chỉ Chủ hộ được cập nhật thành viên chuyển đi.');
        $nk = Db::one("SELECT * FROM nhan_khau WHERE ID_nhan_khau=? AND ID_can_ho=?", [$id, $me['ID_can_ho']]);
        if (!$nk || $nk['loai_cu_dan'] !== 'thanh_vien') throw new BusinessException('Chỉ cập nhật được thành viên trong hộ.');
        Db::q("UPDATE nhan_khau SET trang_thai_duyet='da_roi_di' WHERE ID_nhan_khau=?", [$id]);
        Db::q("UPDATE the_ra_vao SET trang_thai_the='da_huy' WHERE ID_nhan_khau=?", [$id]);
        AuditService::log('thanh_vien_chuyen_di', 'nhan_khau', $id, $nk['ho_ten']);
    }
    /** Hết hạn hợp đồng: khóa tài khoản khách thuê, hủy thẻ, cập nhật trạng thái căn hộ */
    public static function expireTenant(int $nkId): void
    {
        Db::tx(function () use ($nkId) {
            $nk = Db::one("SELECT * FROM nhan_khau WHERE ID_nhan_khau=?", [$nkId]);
            Db::q("UPDATE nhan_khau SET trang_thai_duyet='da_roi_di' WHERE ID_nhan_khau=?", [$nkId]);
            if ($nk['ID_tai_khoan']) Db::q("UPDATE tai_khoan SET trang_thai=0 WHERE ID_tai_khoan=?", [$nk['ID_tai_khoan']]);
            Db::q("UPDATE the_ra_vao SET trang_thai_the='da_huy' WHERE ID_nhan_khau=?", [$nkId]);
            self::syncOccupancy((int)$nk['ID_can_ho']);
            AuditService::log('het_han_hop_dong', 'nhan_khau', $nkId, $nk['ho_ten'], $nk['ID_tai_khoan'] ? (int)$nk['ID_tai_khoan'] : null);
        });
    }
}
