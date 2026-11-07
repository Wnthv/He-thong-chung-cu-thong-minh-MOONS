<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng the_ra_vao – Thẻ RFID và mã vân tay (Bảng 3.18 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('the_ra_vao', function (Blueprint $table) {
            $table->increments('ID_the');
            $table->string('ma_chip_rfid', 30)->nullable();
            $table->string('ma_id_van_tay', 30)->nullable();        // chỉ lưu mã ID đã mã hóa, không lưu ảnh vân tay
            $table->enum('loai_the', ['rfid_xe', 'thang_may', 'van_tay']);
            $table->enum('trang_thai_the', ['kich_hoat', 'tam_khoa', 'bao_mat', 'da_huy'])->default('kich_hoat');
            $table->dateTime('ngay_cap')->useCurrent();
            $table->unsignedInteger('ID_nhan_khau');

            $table->unique('ma_chip_rfid', 'uq_trv_rfid');
            $table->unique('ma_id_van_tay', 'uq_trv_vt');
            $table->foreign('ID_nhan_khau', 'fk_trv_nk')->references('ID_nhan_khau')->on('nhan_khau')->restrictOnDelete();
        });
        $this->check('the_ra_vao', [
            'ck_trv_ma'   => 'ma_chip_rfid IS NOT NULL OR ma_id_van_tay IS NOT NULL',
            'ck_trv_loai' => "(loai_the = 'van_tay' AND ma_id_van_tay IS NOT NULL) OR (loai_the <> 'van_tay' AND ma_chip_rfid IS NOT NULL)",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('the_ra_vao');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
