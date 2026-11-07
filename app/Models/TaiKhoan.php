<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model bảng tai_khoan – Tài khoản đăng nhập và vai trò.
 * Ánh xạ đúng tên bảng, khóa chính và các khóa ngoại trong thiết kế CSDL (mục 3.3, Bảng 3.4).
 */
class TaiKhoan extends Model
{
    protected $table = 'tai_khoan';

    protected $primaryKey = 'ID_tai_khoan';

    /** Bảng không dùng cột created_at/updated_at của Laravel (thời điểm do CSDL tự ghi) */
    public $timestamps = false;

    /** Không đưa mật khẩu đã băm ra ngoài khi chuyển sang mảng/JSON */
    protected $hidden = ['mat_khau_bam'];

    protected $fillable = [
        'ten_dang_nhap',
        'mat_khau_bam',
        'ho_ten',
        'email',
        'so_dien_thoai',
        'vai_tro',
        'trang_thai',
        'so_lan_sai',
        'khoa_den',
        'phai_doi_mat_khau',
        'ngay_tao',
    ];

    /** FK 11 – tài khoản gắn với tối đa 1 nhân khẩu (UQ uq_nk_tk) */
    public function nhanKhau(): HasOne
    {
        return $this->hasOne(NhanKhau::class, 'ID_tai_khoan', 'ID_tai_khoan');
    }

    /** FK 15 */
    public function bieuPhiDaCapNhat(): HasMany
    {
        return $this->hasMany(BieuPhi::class, 'ID_nguoi_cap_nhat', 'ID_tai_khoan');
    }

    /** FK 16 – Lễ tân duyệt */
    public function nhanKhauDaDuyet(): HasMany
    {
        return $this->hasMany(NhanKhau::class, 'ID_nguoi_duyet', 'ID_tai_khoan');
    }

    /** FK 17 – Lễ tân duyệt */
    public function theXeDaDuyet(): HasMany
    {
        return $this->hasMany(TheXe::class, 'ID_nguoi_duyet', 'ID_tai_khoan');
    }

    /** FK 18 – Kế toán nhập chỉ số */
    public function chiSoDaNhap(): HasMany
    {
        return $this->hasMany(ChiSoDienNuoc::class, 'ID_nguoi_nhap', 'ID_tai_khoan');
    }

    /** FK 19 – Kế toán lập */
    public function hoaDonDaLap(): HasMany
    {
        return $this->hasMany(HoaDon::class, 'ID_nguoi_lap', 'ID_tai_khoan');
    }

    /** FK 20 – Kế toán trưởng duyệt */
    public function hoaDonDaDuyet(): HasMany
    {
        return $this->hasMany(HoaDon::class, 'ID_nguoi_duyet', 'ID_tai_khoan');
    }

    /** FK 21 – Kế toán gạch nợ */
    public function giaoDichDaThucHien(): HasMany
    {
        return $this->hasMany(GiaoDichThanhToan::class, 'ID_nguoi_thuc_hien', 'ID_tai_khoan');
    }

    /** FK 22 */
    public function datTienIchDaTao(): HasMany
    {
        return $this->hasMany(DatTienIch::class, 'ID_nguoi_tao', 'ID_tai_khoan');
    }

    /** FK 23 – Kỹ thuật viên phụ trách */
    public function phieuDuocGiao(): HasMany
    {
        return $this->hasMany(PhieuSuCo::class, 'ID_ky_thuat_vien', 'ID_tai_khoan');
    }

    /** FK 24 – Điều phối viên */
    public function phieuDaPhanCong(): HasMany
    {
        return $this->hasMany(PhieuSuCo::class, 'ID_nguoi_phan_cong', 'ID_tai_khoan');
    }

    /** FK 25 */
    public function nguCanhDaCapNhat(): HasMany
    {
        return $this->hasMany(NguCanhAi::class, 'ID_nguoi_cap_nhat', 'ID_tai_khoan');
    }

    /** FK 26 */
    public function nhatKy(): HasMany
    {
        return $this->hasMany(NhatKyThaoTac::class, 'ID_tai_khoan', 'ID_tai_khoan');
    }
}
