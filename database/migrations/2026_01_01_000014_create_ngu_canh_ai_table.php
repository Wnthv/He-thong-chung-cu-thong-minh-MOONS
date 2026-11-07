<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng ngu_canh_ai – Tài liệu ngữ cảnh của Trợ lý AI (Bảng 3.13 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ngu_canh_ai', function (Blueprint $table) {
            $table->increments('ID_ngu_canh');
            $table->string('tieu_de_tai_lieu', 150);
            $table->text('noi_dung_ngu_canh');
            $table->string('loai_tai_lieu', 50);
            $table->enum('trang_thai', ['nhap', 'dang_su_dung'])->default('nhap');
            $table->dateTime('ngay_cap_nhat')->useCurrent();
            $table->unsignedInteger('ID_nguoi_cap_nhat');

            $table->foreign('ID_nguoi_cap_nhat', 'fk_nc_nd')->references('ID_tai_khoan')->on('tai_khoan')->restrictOnDelete();
        });
        $this->check('ngu_canh_ai', [
            'ck_nc_nd' => 'CHAR_LENGTH(noi_dung_ngu_canh) >= 20',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ngu_canh_ai');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
