<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model bảng bieu_phi – Biểu phí dịch vụ (điện 6 bậc, nước, quản lý, xe).
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class BieuPhi extends Model
{
    protected $table = 'bieu_phi';

    protected $primaryKey = 'ID_bieu_phi';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'ma_phi',
        'ten_phi',
        'tu_muc',
        'den_muc',
        'don_gia',
        'don_vi',
        'ngay_cap_nhat',
        'ID_nguoi_cap_nhat',
    ];

    /** FK 15 */
    public function nguoiCapNhat(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'ID_nguoi_cap_nhat', 'ID_tai_khoan');
    }
}
