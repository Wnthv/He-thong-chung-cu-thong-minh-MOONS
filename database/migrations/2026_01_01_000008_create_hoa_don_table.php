<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng hoa_don – Hóa đơn tháng của căn hộ (Bảng 3.12 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hoa_don', function (Blueprint $table) {
            $table->increments('ID_hoa_don');
            $table->string('ma_hoa_don', 30);
            $table->string('ky_cuoc', 7);
            $table->decimal('tong_tien', 14, 2)->default(0);        // [DB] trigger luôn = SUM(chi_tiet.thanh_tien)
            $table->enum('trang_thai_thanh_toan', ['cho_duyet', 'chua_thanh_toan', 'da_thanh_toan', 'da_huy'])->default('cho_duyet');
            $table->dateTime('ngay_lap')->useCurrent();
            $table->dateTime('ngay_phat_hanh')->nullable();
            $table->date('han_thanh_toan')->nullable();
            $table->dateTime('ngay_thanh_toan')->nullable();
            $table->enum('phuong_thuc_thanh_toan', ['vnpay', 'tien_mat', 'chuyen_khoan'])->nullable();
            $table->string('ly_do_huy', 255)->nullable();
            $table->unsignedInteger('ID_can_ho');
            $table->unsignedInteger('ID_nguoi_lap');
            $table->unsignedInteger('ID_nguoi_duyet')->nullable();
            // [DB] mỗi căn hộ chỉ có 1 hóa đơn còn hiệu lực trong 1 kỳ (hóa đơn đã hủy không tính)
            $table->string('ky_hieu_luc', 7)->storedAs("CASE WHEN trang_thai_thanh_toan <> 'da_huy' THEN ky_cuoc END");

            $table->unique('ma_hoa_don', 'uq_hd_ma');
            $table->unique(['ID_can_ho', 'ky_hieu_luc'], 'uq_hd_ky');
            $table->foreign('ID_can_ho', 'fk_hd_ch')->references('ID_can_ho')->on('can_ho')->restrictOnDelete();
            $table->foreign('ID_nguoi_lap', 'fk_hd_nl')->references('ID_tai_khoan')->on('tai_khoan')->restrictOnDelete();
            $table->foreign('ID_nguoi_duyet', 'fk_hd_nd')->references('ID_tai_khoan')->on('tai_khoan')->restrictOnDelete();
        });
        $this->check('hoa_don', [
            'ck_hd_ky'    => "ky_cuoc REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'",
            'ck_hd_tien'  => 'tong_tien >= 0',
            'ck_hd_duyet' => "trang_thai_thanh_toan NOT IN ('chua_thanh_toan','da_thanh_toan') OR (ID_nguoi_duyet IS NOT NULL AND ngay_phat_hanh IS NOT NULL AND han_thanh_toan IS NOT NULL)",
            'ck_hd_tt'    => "trang_thai_thanh_toan <> 'da_thanh_toan' OR (ngay_thanh_toan IS NOT NULL AND phuong_thuc_thanh_toan IS NOT NULL)",
            'ck_hd_huy'   => "trang_thai_thanh_toan <> 'da_huy' OR ly_do_huy IS NOT NULL",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hoa_don');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
