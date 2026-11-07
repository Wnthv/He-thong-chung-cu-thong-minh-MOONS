<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng can_ho – Căn hộ và shophouse (Bảng 3.6 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('can_ho', function (Blueprint $table) {
            $table->increments('ID_can_ho');
            $table->string('ma_can_ho', 10);
            $table->char('thap_nha', 1);
            $table->unsignedTinyInteger('so_tang');
            $table->enum('loai_can', ['can_ho', 'shophouse'])->default('can_ho');
            $table->decimal('dien_tich', 6, 2);
            $table->enum('trang_thai_o', ['trong', 'chu_ho_o', 'cho_thue'])->default('trong');   // [SVC] đồng bộ theo nhân khẩu

            $table->unique('ma_can_ho', 'uq_ch_ma');
        });
        $this->check('can_ho', [
            'ck_ch_thap' => "thap_nha IN ('A','B','C','D')",
            'ck_ch_tang' => 'so_tang BETWEEN 1 AND 28',
            'ck_ch_dt'   => 'dien_tich > 0',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('can_ho');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
