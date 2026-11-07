<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng nhan_khau – Hồ sơ cư dân (chủ hộ, khách thuê, thành viên) (Bảng 3.14 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nhan_khau', function (Blueprint $table) {
            $table->increments('ID_nhan_khau');
            $table->string('ho_ten', 100);
            $table->date('ngay_sinh')->nullable();
            $table->enum('gioi_tinh', ['nam', 'nu'])->nullable();
            $table->string('so_dien_thoai', 15)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('so_cccd', 12)->nullable();
            $table->string('quan_he_chu_ho', 30)->default('Chủ hộ');
            $table->enum('loai_cu_dan', ['chu_ho', 'khach_thue', 'thanh_vien']);
            $table->string('so_hop_dong', 30)->nullable();
            $table->date('ngay_bat_dau_hop_dong')->nullable();
            $table->date('ngay_ket_thuc_hop_dong')->nullable();
            $table->string('anh_giay_to', 255)->nullable();
            $table->enum('trang_thai_duyet', ['cho_duyet', 'da_duyet', 'tu_choi', 'da_roi_di'])->default('cho_duyet');
            $table->string('ly_do_tu_choi', 255)->nullable();
            $table->dateTime('ngay_duyet')->nullable();
            $table->unsignedInteger('ID_can_ho');
            $table->unsignedInteger('ID_tai_khoan')->nullable();     // quan hệ 1 – 0..1 (thành viên không có tài khoản)
            $table->unsignedInteger('ID_nguoi_duyet')->nullable();

            $table->unique('so_cccd', 'uq_nk_cccd');
            $table->unique('ID_tai_khoan', 'uq_nk_tk');
            $table->foreign('ID_can_ho', 'fk_nk_ch')->references('ID_can_ho')->on('can_ho')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('ID_tai_khoan', 'fk_nk_tk')->references('ID_tai_khoan')->on('tai_khoan')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('ID_nguoi_duyet', 'fk_nk_nd')->references('ID_tai_khoan')->on('tai_khoan')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->check('nhan_khau', [
            'ck_nk_cccd'     => "so_cccd IS NULL OR so_cccd REGEXP '^[0-9]{12}$'",
            'ck_nk_hop_dong' => "loai_cu_dan <> 'khach_thue' OR (ngay_bat_dau_hop_dong IS NOT NULL AND ngay_ket_thuc_hop_dong IS NOT NULL AND ngay_ket_thuc_hop_dong >= ngay_bat_dau_hop_dong AND so_hop_dong IS NOT NULL)",
            'ck_nk_tu_choi'  => "trang_thai_duyet <> 'tu_choi' OR ly_do_tu_choi IS NOT NULL",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('nhan_khau');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
