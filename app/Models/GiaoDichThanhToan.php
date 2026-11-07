<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model bảng giao_dich_thanh_toan – Giao dịch VNPay và gạch nợ thủ công.
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class GiaoDichThanhToan extends Model
{
    protected $table = 'giao_dich_thanh_toan';

    protected $primaryKey = 'ID_giao_dich';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'ma_giao_dich_ref',
        'hinh_thuc',
        'so_tien',
        'ma_ngan_hang',
        'ma_gd_ngan_hang',
        'ma_phan_hoi',
        'chuoi_bam_secure_hash',
        'trang_thai',
        'ghi_chu',
        'so_chung_tu',
        'nguoi_nop',
        'ngay_giao_dich',
        'ID_hoa_don',
        'ID_nguoi_thuc_hien',
    ];

    /** Cột sinh (generated column) do CSDL tự tính, không nằm trong $fillable: hd_thanh_cong */

    /** FK 14 */
    public function hoaDon(): BelongsTo
    {
        return $this->belongsTo(HoaDon::class, 'ID_hoa_don', 'ID_hoa_don');
    }

    /** FK 21 – NULL với VNPay */
    public function nguoiThucHien(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'ID_nguoi_thuc_hien', 'ID_tai_khoan');
    }
}
