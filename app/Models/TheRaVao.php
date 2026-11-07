<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model bảng the_ra_vao – Thẻ RFID và mã vân tay.
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class TheRaVao extends Model
{
    protected $table = 'the_ra_vao';

    protected $primaryKey = 'ID_the';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'ma_chip_rfid',
        'ma_id_van_tay',
        'loai_the',
        'trang_thai_the',
        'ngay_cap',
        'ID_nhan_khau',
    ];

    /** FK 8 */
    public function nhanKhau(): BelongsTo
    {
        return $this->belongsTo(NhanKhau::class, 'ID_nhan_khau', 'ID_nhan_khau');
    }
}
