<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng dich_vu_tien_ich – Danh mục tiện ích (Bảng 3.10 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dich_vu_tien_ich', function (Blueprint $table) {
            $table->increments('ID_tien_ich');
            $table->string('ma_tien_ich', 10);
            $table->string('ten_tien_ich', 100);
            $table->unsignedSmallInteger('suc_chua_toi_da');
            $table->time('gio_mo_cua');
            $table->time('gio_dong_cua');
            $table->unsignedSmallInteger('thoi_luong_khung')->default(120);   // phút
            $table->tinyInteger('doc_quyen')->default(1);           // 1: mỗi khung giờ 1 lượt đặt; 0: dùng chung theo sức chứa
            $table->unsignedTinyInteger('han_muc_tuan')->nullable(); // số lượt/căn hộ/tuần (NULL = không giới hạn)
            $table->enum('trang_thai_hoat_dong', ['hoat_dong', 'tam_dung'])->default('hoat_dong');

            $table->unique('ma_tien_ich', 'uq_ti_ma');
        });
        $this->check('dich_vu_tien_ich', [
            'ck_ti_gio' => 'gio_dong_cua > gio_mo_cua',
            'ck_ti_sc'  => 'suc_chua_toi_da > 0',
            'ck_ti_dq'  => 'doc_quyen IN (0,1)',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('dich_vu_tien_ich');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
