<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng the_xe – Hồ sơ đăng ký phương tiện (Bảng 3.19 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('the_xe', function (Blueprint $table) {
            $table->increments('ID_the_xe');
            $table->string('bien_so_xe', 15);
            $table->enum('loai_xe', ['o_to', 'xe_may']);
            $table->string('nhan_hieu', 50);
            $table->string('mau_xe', 30);
            $table->string('anh_ca_vet_xe', 255);
            $table->enum('trang_thai_duyet', ['cho_duyet', 'yeu_cau_bo_sung', 'da_duyet', 'tu_choi', 'da_huy'])->default('cho_duyet');
            $table->string('ly_do', 255)->nullable();
            $table->dateTime('ngay_gui')->useCurrent();
            $table->dateTime('ngay_duyet')->nullable();
            $table->unsignedInteger('ID_can_ho');
            $table->unsignedInteger('ID_nhan_khau');
            $table->unsignedInteger('ID_nguoi_duyet')->nullable();
            // [DB] biển số chỉ duy nhất trong các hồ sơ còn hiệu lực (hồ sơ đã hủy/từ chối được phép trùng)
            $table->string('bien_so_hieu_luc', 15)
                ->storedAs("CASE WHEN trang_thai_duyet IN ('cho_duyet','yeu_cau_bo_sung','da_duyet') THEN bien_so_xe END");

            $table->unique('bien_so_hieu_luc', 'uq_tx_bien_so');
            $table->foreign('ID_can_ho', 'fk_tx_ch')->references('ID_can_ho')->on('can_ho')->restrictOnDelete();
            $table->foreign('ID_nhan_khau', 'fk_tx_nk')->references('ID_nhan_khau')->on('nhan_khau')->restrictOnDelete();
            $table->foreign('ID_nguoi_duyet', 'fk_tx_nd')->references('ID_tai_khoan')->on('tai_khoan')->restrictOnDelete();
        });
        // [SVC] định mức tối đa 2 ô tô + 4 xe máy / căn hộ: VehicleService (khóa dòng căn hộ FOR UPDATE)
        $this->check('the_xe', [
            'ck_tx_bien_so' => "bien_so_xe REGEXP '^[0-9]{2}[A-Z]{1,2}[0-9]?-([0-9]{3}\\\\.?[0-9]{2}|[0-9]{4})$'",
            'ck_tx_ly_do'   => "trang_thai_duyet NOT IN ('tu_choi','yeu_cau_bo_sung') OR ly_do IS NOT NULL",
            'ck_tx_duyet'   => "trang_thai_duyet <> 'da_duyet' OR (ID_nguoi_duyet IS NOT NULL AND ngay_duyet IS NOT NULL)",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('the_xe');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
