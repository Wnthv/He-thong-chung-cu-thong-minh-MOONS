<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model bảng can_ho – Căn hộ và shophouse.
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class CanHo extends Model
{
    protected $table = 'can_ho';

    protected $primaryKey = 'ID_can_ho';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'ma_can_ho',
        'thap_nha',
        'so_tang',
        'loai_can',
        'dien_tich',
        'trang_thai_o',
    ];

    /** FK 1 */
    public function nhanKhau(): HasMany
    {
        return $this->hasMany(NhanKhau::class, 'ID_can_ho', 'ID_can_ho');
    }

    /** FK 2 – định mức BR-05 */
    public function theXe(): HasMany
    {
        return $this->hasMany(TheXe::class, 'ID_can_ho', 'ID_can_ho');
    }

    /** FK 3 – mỗi kỳ tối đa 1 bản ghi (uq_cs_ky) */
    public function chiSoDienNuoc(): HasMany
    {
        return $this->hasMany(ChiSoDienNuoc::class, 'ID_can_ho', 'ID_can_ho');
    }

    /** FK 4 – mỗi kỳ tối đa 1 hóa đơn hiệu lực (uq_hd_ky) */
    public function hoaDon(): HasMany
    {
        return $this->hasMany(HoaDon::class, 'ID_can_ho', 'ID_can_ho');
    }

    /** FK 5 */
    public function datTienIch(): HasMany
    {
        return $this->hasMany(DatTienIch::class, 'ID_can_ho', 'ID_can_ho');
    }

    /** FK 6 */
    public function phieuSuCo(): HasMany
    {
        return $this->hasMany(PhieuSuCo::class, 'ID_can_ho', 'ID_can_ho');
    }
}
