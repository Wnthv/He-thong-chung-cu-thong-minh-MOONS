<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model bảng the_xe – Hồ sơ đăng ký phương tiện.
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class TheXe extends Model
{
    protected $table = 'the_xe';

    protected $primaryKey = 'ID_the_xe';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'bien_so_xe',
        'loai_xe',
        'nhan_hieu',
        'mau_xe',
        'anh_ca_vet_xe',
        'trang_thai_duyet',
        'ly_do',
        'ngay_gui',
        'ngay_duyet',
        'ID_can_ho',
        'ID_nhan_khau',
        'ID_nguoi_duyet',
    ];

    /** Cột sinh (generated column) do CSDL tự tính, không nằm trong $fillable: bien_so_hieu_luc */

    /** FK 2 */
    public function canHo(): BelongsTo
    {
        return $this->belongsTo(CanHo::class, 'ID_can_ho', 'ID_can_ho');
    }

    /** FK 7 */
    public function nhanKhau(): BelongsTo
    {
        return $this->belongsTo(NhanKhau::class, 'ID_nhan_khau', 'ID_nhan_khau');
    }

    /** FK 17 */
    public function nguoiDuyet(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'ID_nguoi_duyet', 'ID_tai_khoan');
    }
}
