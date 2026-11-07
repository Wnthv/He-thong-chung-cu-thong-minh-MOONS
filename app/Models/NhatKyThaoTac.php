<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model bảng nhat_ky_thao_tac – Nhật ký thao tác chỉ ghi thêm (trigger chặn sửa/xóa).
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class NhatKyThaoTac extends Model
{
    protected $table = 'nhat_ky_thao_tac';

    protected $primaryKey = 'ID_nhat_ky';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    protected $fillable = [
        'hanh_dong',
        'doi_tuong',
        'ID_doi_tuong',
        'noi_dung',
        'dia_chi_ip',
        'thoi_gian',
        'ID_tai_khoan',
    ];

    /** FK 26 – NULL với thao tác chưa đăng nhập */
    public function taiKhoan(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'ID_tai_khoan', 'ID_tai_khoan');
    }
}
