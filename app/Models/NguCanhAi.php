<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model bảng ngu_canh_ai – Tài liệu ngữ cảnh của Trợ lý AI.
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class NguCanhAi extends Model
{
    protected $table = 'ngu_canh_ai';

    protected $primaryKey = 'ID_ngu_canh';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'tieu_de_tai_lieu',
        'noi_dung_ngu_canh',
        'loai_tai_lieu',
        'trang_thai',
        'ngay_cap_nhat',
        'ID_nguoi_cap_nhat',
    ];

    /** FK 25 */
    public function nguoiCapNhat(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'ID_nguoi_cap_nhat', 'ID_tai_khoan');
    }
}
