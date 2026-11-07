<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model bảng hoa_don – Hóa đơn tháng của căn hộ.
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class HoaDon extends Model
{
    protected $table = 'hoa_don';

    protected $primaryKey = 'ID_hoa_don';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'ma_hoa_don',
        'ky_cuoc',
        'tong_tien',
        'trang_thai_thanh_toan',
        'ngay_lap',
        'ngay_phat_hanh',
        'han_thanh_toan',
        'ngay_thanh_toan',
        'phuong_thuc_thanh_toan',
        'ly_do_huy',
        'ID_can_ho',
        'ID_nguoi_lap',
        'ID_nguoi_duyet',
    ];

    /** Cột sinh (generated column) do CSDL tự tính, không nằm trong $fillable: ky_hieu_luc */

    /** FK 4 */
    public function canHo(): BelongsTo
    {
        return $this->belongsTo(CanHo::class, 'ID_can_ho', 'ID_can_ho');
    }

    /** FK 19 */
    public function nguoiLap(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'ID_nguoi_lap', 'ID_tai_khoan');
    }

    /** FK 20 – phải khác người lập */
    public function nguoiDuyet(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'ID_nguoi_duyet', 'ID_tai_khoan');
    }

    /** FK 13 – dòng cước */
    public function chiTiet(): HasMany
    {
        return $this->hasMany(ChiTietHoaDon::class, 'ID_hoa_don', 'ID_hoa_don');
    }

    /** FK 14 – tối đa 1 giao dịch thành công (uq_gd_hd_tc) */
    public function giaoDich(): HasMany
    {
        return $this->hasMany(GiaoDichThanhToan::class, 'ID_hoa_don', 'ID_hoa_don');
    }
}
