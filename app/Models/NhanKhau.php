<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model bảng nhan_khau – Hồ sơ cư dân (chủ hộ, khách thuê, thành viên).
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class NhanKhau extends Model
{
    protected $table = 'nhan_khau';

    protected $primaryKey = 'ID_nhan_khau';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'ho_ten',
        'ngay_sinh',
        'gioi_tinh',
        'so_dien_thoai',
        'email',
        'so_cccd',
        'quan_he_chu_ho',
        'loai_cu_dan',
        'so_hop_dong',
        'ngay_bat_dau_hop_dong',
        'ngay_ket_thuc_hop_dong',
        'anh_giay_to',
        'trang_thai_duyet',
        'ly_do_tu_choi',
        'ngay_duyet',
        'ID_can_ho',
        'ID_tai_khoan',
        'ID_nguoi_duyet',
    ];

    /** FK 1 */
    public function canHo(): BelongsTo
    {
        return $this->belongsTo(CanHo::class, 'ID_can_ho', 'ID_can_ho');
    }

    /** FK 11 – NULL với thành viên không có tài khoản */
    public function taiKhoan(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'ID_tai_khoan', 'ID_tai_khoan');
    }

    /** FK 16 */
    public function nguoiDuyet(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'ID_nguoi_duyet', 'ID_tai_khoan');
    }

    /** FK 7 – chủ phương tiện */
    public function theXe(): HasMany
    {
        return $this->hasMany(TheXe::class, 'ID_nhan_khau', 'ID_nhan_khau');
    }

    /** FK 8 */
    public function theRaVao(): HasMany
    {
        return $this->hasMany(TheRaVao::class, 'ID_nhan_khau', 'ID_nhan_khau');
    }

    /** FK 9 */
    public function datTienIch(): HasMany
    {
        return $this->hasMany(DatTienIch::class, 'ID_nhan_khau', 'ID_nhan_khau');
    }

    /** FK 10 */
    public function phieuSuCoDaGui(): HasMany
    {
        return $this->hasMany(PhieuSuCo::class, 'ID_nguoi_gui', 'ID_nhan_khau');
    }
}
