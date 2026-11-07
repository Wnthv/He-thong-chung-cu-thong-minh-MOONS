<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model bảng chi_tiet_hoa_don – Dòng cước của hóa đơn.
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class ChiTietHoaDon extends Model
{
    protected $table = 'chi_tiet_hoa_don';

    protected $primaryKey = 'ID_chi_tiet';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'loai_cuoc',
        'dien_giai',
        'so_luong',
        'don_gia',
        'thanh_tien',
        'ID_hoa_don',
    ];

    /** FK 13 */
    public function hoaDon(): BelongsTo
    {
        return $this->belongsTo(HoaDon::class, 'ID_hoa_don', 'ID_hoa_don');
    }
}
