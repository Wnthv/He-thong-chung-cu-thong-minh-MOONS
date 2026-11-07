<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng giao_dich_thanh_toan – Giao dịch VNPay và gạch nợ thủ công (Bảng 3.11 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giao_dich_thanh_toan', function (Blueprint $table) {
            $table->increments('ID_giao_dich');
            $table->string('ma_giao_dich_ref', 40);                 // vnp_TxnRef hoặc số phiếu thu
            $table->enum('hinh_thuc', ['vnpay', 'tien_mat', 'chuyen_khoan']);
            $table->decimal('so_tien', 14, 2);
            $table->string('ma_ngan_hang', 20)->nullable();
            $table->string('ma_gd_ngan_hang', 40)->nullable();      // vnp_TransactionNo
            $table->string('ma_phan_hoi', 5)->nullable();           // vnp_ResponseCode
            $table->string('chuoi_bam_secure_hash', 256)->nullable();
            $table->enum('trang_thai', ['khoi_tao', 'thanh_cong', 'that_bai', 'ngoai_le'])->default('khoi_tao');
            $table->string('ghi_chu', 255)->nullable();
            $table->string('so_chung_tu', 40)->nullable();          // số biên lai/phiếu thu (thủ công)
            $table->string('nguoi_nop', 100)->nullable();
            $table->dateTime('ngay_giao_dich')->useCurrent();
            $table->unsignedInteger('ID_hoa_don');
            $table->unsignedInteger('ID_nguoi_thuc_hien')->nullable();
            // [DB] mỗi hóa đơn chỉ có tối đa 1 giao dịch thành công (chống gạch nợ 2 lần)
            $table->unsignedInteger('hd_thanh_cong')->storedAs("CASE WHEN trang_thai = 'thanh_cong' THEN ID_hoa_don END");

            $table->unique('ma_giao_dich_ref', 'uq_gd_ref');
            $table->unique('hd_thanh_cong', 'uq_gd_hd_tc');
            $table->foreign('ID_hoa_don', 'fk_gd_hd')->references('ID_hoa_don')->on('hoa_don')->restrictOnDelete();
            $table->foreign('ID_nguoi_thuc_hien', 'fk_gd_nd')->references('ID_tai_khoan')->on('tai_khoan')->restrictOnDelete();
        });
        $this->check('giao_dich_thanh_toan', [
            'ck_gd_tien'     => 'so_tien > 0',
            'ck_gd_thu_cong' => "hinh_thuc = 'vnpay' OR (so_chung_tu IS NOT NULL AND ID_nguoi_thuc_hien IS NOT NULL)",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('giao_dich_thanh_toan');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
