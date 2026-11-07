<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng bieu_phi – Biểu phí dịch vụ (điện 6 bậc, nước, quản lý, xe) (Bảng 3.5 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bieu_phi', function (Blueprint $table) {
            $table->increments('ID_bieu_phi');
            $table->string('ma_phi', 20);                           // DIEN_B1..DIEN_B6, NUOC, QUAN_LY, XE_O_TO, XE_MAY
            $table->string('ten_phi', 100);
            $table->unsignedInteger('tu_muc')->nullable();
            $table->unsignedInteger('den_muc')->nullable();         // NULL = không giới hạn
            $table->decimal('don_gia', 12, 2);
            $table->string('don_vi', 20);
            $table->dateTime('ngay_cap_nhat')->useCurrent();
            $table->unsignedInteger('ID_nguoi_cap_nhat')->nullable();

            $table->unique('ma_phi', 'uq_bp_ma');
            $table->foreign('ID_nguoi_cap_nhat', 'fk_bp_nd')->references('ID_tai_khoan')->on('tai_khoan')->restrictOnDelete();
        });
        $this->check('bieu_phi', [
            'ck_bp_gia' => 'don_gia >= 0',
            'ck_bp_muc' => 'den_muc IS NULL OR tu_muc IS NULL OR den_muc >= tu_muc',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bieu_phi');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
