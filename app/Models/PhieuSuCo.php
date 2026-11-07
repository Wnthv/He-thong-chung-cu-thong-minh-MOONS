<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model bảng phieu_su_co – Phiếu báo hỏng và tiến độ Kanban (bảng trung gian căn hộ – kỹ thuật viên).
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class PhieuSuCo extends Model
{
    protected $table = 'phieu_su_co';

    protected $primaryKey = 'ID_phieu';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'ma_phieu',
        'tieu_de',
        'mo_ta_chi_tiet',
        'pham_vi',
        'vi_tri_su_co',
        'muc_do_uu_tien',
        'trang_thai_kanban',
        'anh_hien_truong',
        'anh_nghiem_thu',
        'vat_tu_tieu_hao',
        'ghi_chu_ky_thuat',
        'ly_do_tu_choi',
        'diem_danh_gia',
        'so_lan_mo_lai',
        'ngay_tao',
        'ngay_phan_cong',
        'ngay_hoan_thanh',
        'ID_can_ho',
        'ID_nguoi_gui',
        'ID_ky_thuat_vien',
        'ID_nguoi_phan_cong',
    ];

    /** FK 6 */
    public function canHo(): BelongsTo
    {
        return $this->belongsTo(CanHo::class, 'ID_can_ho', 'ID_can_ho');
    }

    /** FK 10 */
    public function nguoiGui(): BelongsTo
    {
        return $this->belongsTo(NhanKhau::class, 'ID_nguoi_gui', 'ID_nhan_khau');
    }

    /** FK 23 */
    public function kyThuatVien(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'ID_ky_thuat_vien', 'ID_tai_khoan');
    }

    /** FK 24 */
    public function nguoiPhanCong(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'ID_nguoi_phan_cong', 'ID_tai_khoan');
    }
}
