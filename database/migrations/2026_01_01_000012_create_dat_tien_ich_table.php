<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng dat_tien_ich – Lượt đặt và lịch khóa bảo trì (Bảng 3.9 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dat_tien_ich', function (Blueprint $table) {
            $table->increments('ID_dat_lich');
            $table->string('ma_ve', 40);                            // chuỗi mã QR vé điện tử
            $table->enum('loai', ['dat_cho', 'bao_tri'])->default('dat_cho');
            $table->date('ngay_dat');
            $table->time('khung_gio_bat_dau');
            $table->time('khung_gio_ket_thuc');
            $table->unsignedSmallInteger('so_nguoi')->nullable();
            $table->enum('trang_thai_dat', ['da_xac_nhan', 'da_su_dung', 'da_huy', 'huy_muon'])->default('da_xac_nhan');
            $table->dateTime('ngay_tao')->useCurrent();
            $table->dateTime('ngay_huy')->nullable();
            $table->unsignedInteger('ID_tien_ich');
            $table->unsignedInteger('ID_nhan_khau')->nullable();
            $table->unsignedInteger('ID_can_ho')->nullable();
            $table->unsignedInteger('ID_nguoi_tao');

            $table->unique('ma_ve', 'uq_dti_ve');
            $table->foreign('ID_tien_ich', 'fk_dti_ti')->references('ID_tien_ich')->on('dich_vu_tien_ich')->restrictOnDelete();
            $table->foreign('ID_nhan_khau', 'fk_dti_nk')->references('ID_nhan_khau')->on('nhan_khau')->restrictOnDelete();
            $table->foreign('ID_can_ho', 'fk_dti_ch')->references('ID_can_ho')->on('can_ho')->restrictOnDelete();
            $table->foreign('ID_nguoi_tao', 'fk_dti_nt')->references('ID_tai_khoan')->on('tai_khoan')->restrictOnDelete();
        });
        // [SVC] chống trùng khung giờ, sức chứa, hạn mức tuần, chặn nợ quá hạn: BookingService (SELECT ... FOR UPDATE)
        $this->check('dat_tien_ich', [
            'ck_dti_gio'  => 'khung_gio_ket_thuc > khung_gio_bat_dau',
            'ck_dti_loai' => "loai = 'bao_tri' OR (ID_nhan_khau IS NOT NULL AND ID_can_ho IS NOT NULL AND so_nguoi > 0)",
            'ck_dti_huy'  => "trang_thai_dat NOT IN ('da_huy','huy_muon') OR ngay_huy IS NOT NULL",
        ]);
        Schema::table('dat_tien_ich', function (Blueprint $table) {
            $table->index(['ID_tien_ich', 'ngay_dat', 'trang_thai_dat'], 'ix_dti_lich');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dat_tien_ich');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
