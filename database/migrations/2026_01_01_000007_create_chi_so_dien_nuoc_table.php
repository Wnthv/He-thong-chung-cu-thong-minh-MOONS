<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng chi_so_dien_nuoc – Chỉ số công tơ theo kỳ (Bảng 3.7 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chi_so_dien_nuoc', function (Blueprint $table) {
            $table->increments('ID_chi_so');
            $table->string('ky_cuoc', 7);                           // YYYY-MM
            $table->unsignedInteger('chi_so_dien_cu');
            $table->unsignedInteger('chi_so_dien_moi');
            $table->unsignedInteger('chi_so_nuoc_cu');
            $table->unsignedInteger('chi_so_nuoc_moi');
            $table->string('ghi_chu_xac_minh', 255)->nullable();    // [SVC] bắt buộc khi tiêu thụ > 3 lần TB 3 kỳ trước
            $table->tinyInteger('da_khoa_so')->default(0);
            $table->dateTime('ngay_nhap')->useCurrent();
            $table->unsignedInteger('ID_can_ho');
            $table->unsignedInteger('ID_nguoi_nhap');

            $table->unique(['ID_can_ho', 'ky_cuoc'], 'uq_cs_ky');
            $table->foreign('ID_can_ho', 'fk_cs_ch')->references('ID_can_ho')->on('can_ho')->restrictOnDelete();
            $table->foreign('ID_nguoi_nhap', 'fk_cs_nn')->references('ID_tai_khoan')->on('tai_khoan')->restrictOnDelete();
        });
        $this->check('chi_so_dien_nuoc', [
            'ck_cs_ky'   => "ky_cuoc REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'",
            'ck_cs_dien' => 'chi_so_dien_moi >= chi_so_dien_cu',
            'ck_cs_nuoc' => 'chi_so_nuoc_moi >= chi_so_nuoc_cu',
            'ck_cs_khoa' => 'da_khoa_so IN (0,1)',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('chi_so_dien_nuoc');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
