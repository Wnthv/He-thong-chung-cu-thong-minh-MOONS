<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model bảng dat_tien_ich – Lượt đặt và lịch khóa bảo trì (bảng trung gian căn hộ – tiện ích).
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class DatTienIch extends Model
{
    protected $table = 'dat_tien_ich';

    protected $primaryKey = 'ID_dat_lich';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'ma_ve',
        'loai',
        'ngay_dat',
        'khung_gio_bat_dau',
        'khung_gio_ket_thuc',
        'so_nguoi',
        'trang_thai_dat',
        'ngay_tao',
        'ngay_huy',
        'ID_tien_ich',
        'ID_nhan_khau',
        'ID_can_ho',
        'ID_nguoi_tao',
    ];

    /** FK 12 */
    public function tienIch(): BelongsTo
    {
        return $this->belongsTo(DichVuTienIch::class, 'ID_tien_ich', 'ID_tien_ich');
    }

    /** FK 9 – NULL khi khóa bảo trì */
    public function nhanKhau(): BelongsTo
    {
        return $this->belongsTo(NhanKhau::class, 'ID_nhan_khau', 'ID_nhan_khau');
    }

    /** FK 5 – NULL khi khóa bảo trì */
    public function canHo(): BelongsTo
    {
        return $this->belongsTo(CanHo::class, 'ID_can_ho', 'ID_can_ho');
    }

    /** FK 22 */
    public function nguoiTao(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'ID_nguoi_tao', 'ID_tai_khoan');
    }
}
