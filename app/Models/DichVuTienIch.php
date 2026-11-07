<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model bảng dich_vu_tien_ich – Danh mục tiện ích.
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class DichVuTienIch extends Model
{
    protected $table = 'dich_vu_tien_ich';

    protected $primaryKey = 'ID_tien_ich';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'ma_tien_ich',
        'ten_tien_ich',
        'suc_chua_toi_da',
        'gio_mo_cua',
        'gio_dong_cua',
        'thoi_luong_khung',
        'doc_quyen',
        'han_muc_tuan',
        'trang_thai_hoat_dong',
    ];

    /** FK 12 */
    public function datTienIch(): HasMany
    {
        return $this->hasMany(DatTienIch::class, 'ID_tien_ich', 'ID_tien_ich');
    }
}
