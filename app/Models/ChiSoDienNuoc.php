<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model bảng chi_so_dien_nuoc – Chỉ số công tơ theo kỳ.
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class ChiSoDienNuoc extends Model
{
    protected $table = 'chi_so_dien_nuoc';

    protected $primaryKey = 'ID_chi_so';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'ky_cuoc',
        'chi_so_dien_cu',
        'chi_so_dien_moi',
        'chi_so_nuoc_cu',
        'chi_so_nuoc_moi',
        'ghi_chu_xac_minh',
        'da_khoa_so',
        'ngay_nhap',
        'ID_can_ho',
        'ID_nguoi_nhap',
    ];

    /** FK 3 */
    public function canHo(): BelongsTo
    {
        return $this->belongsTo(CanHo::class, 'ID_can_ho', 'ID_can_ho');
    }

    /** FK 18 */
    public function nguoiNhap(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'ID_nguoi_nhap', 'ID_tai_khoan');
    }
}
