<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng chi_tiet_hoa_don – Dòng cước của hóa đơn (Bảng 3.8 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chi_tiet_hoa_don', function (Blueprint $table) {
            $table->increments('ID_chi_tiet');
            $table->enum('loai_cuoc', ['dien', 'nuoc', 'quan_ly', 'gui_xe_o_to', 'gui_xe_may']);
            $table->string('dien_giai', 150);
            $table->decimal('so_luong', 12, 2);
            $table->decimal('don_gia', 12, 2);                      // chụp đơn giá tại thời điểm lập (BR-09)
            $table->decimal('thanh_tien', 14, 2);
            $table->unsignedInteger('ID_hoa_don');

            $table->foreign('ID_hoa_don', 'fk_cthd_hd')->references('ID_hoa_don')->on('hoa_don')->restrictOnDelete();
        });
        $this->check('chi_tiet_hoa_don', [
            'ck_cthd_sl'  => 'so_luong >= 0',
            'ck_cthd_gia' => 'don_gia >= 0',
            'ck_cthd_tt'  => 'thanh_tien = ROUND(so_luong * don_gia, 0)',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('chi_tiet_hoa_don');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
