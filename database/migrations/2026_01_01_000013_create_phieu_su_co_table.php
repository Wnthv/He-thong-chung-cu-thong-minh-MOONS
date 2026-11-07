<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng phieu_su_co – Phiếu báo hỏng và tiến độ Kanban (Bảng 3.16 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phieu_su_co', function (Blueprint $table) {
            $table->increments('ID_phieu');
            $table->string('ma_phieu', 20);
            $table->string('tieu_de', 150);
            $table->text('mo_ta_chi_tiet');
            $table->enum('pham_vi', ['can_ho', 'khu_vuc_chung']);
            $table->string('vi_tri_su_co', 150)->nullable();
            $table->enum('muc_do_uu_tien', ['binh_thuong', 'khan_cap'])->default('binh_thuong');
            $table->enum('trang_thai_kanban', ['cho_tiep_nhan', 'da_phan_cong', 'dang_xu_ly', 'cho_linh_kien',
                'cho_nghiem_thu', 'hoan_tat', 'tu_choi', 'da_huy'])->default('cho_tiep_nhan');
            $table->string('anh_hien_truong', 255);
            $table->string('anh_nghiem_thu', 255)->nullable();
            $table->string('vat_tu_tieu_hao', 255)->nullable();
            $table->string('ghi_chu_ky_thuat', 500)->nullable();
            $table->string('ly_do_tu_choi', 255)->nullable();
            $table->unsignedTinyInteger('diem_danh_gia')->nullable();
            $table->unsignedTinyInteger('so_lan_mo_lai')->default(0);
            $table->dateTime('ngay_tao')->useCurrent();
            $table->dateTime('ngay_phan_cong')->nullable();
            $table->dateTime('ngay_hoan_thanh')->nullable();
            $table->unsignedInteger('ID_can_ho');
            $table->unsignedInteger('ID_nguoi_gui');
            $table->unsignedInteger('ID_ky_thuat_vien')->nullable();
            $table->unsignedInteger('ID_nguoi_phan_cong')->nullable();

            $table->unique('ma_phieu', 'uq_psc_ma');
            $table->foreign('ID_can_ho', 'fk_psc_ch')->references('ID_can_ho')->on('can_ho')->restrictOnDelete();
            $table->foreign('ID_nguoi_gui', 'fk_psc_ng')->references('ID_nhan_khau')->on('nhan_khau')->restrictOnDelete();
            $table->foreign('ID_ky_thuat_vien', 'fk_psc_ktv')->references('ID_tai_khoan')->on('tai_khoan')->restrictOnDelete();
            $table->foreign('ID_nguoi_phan_cong', 'fk_psc_npc')->references('ID_tai_khoan')->on('tai_khoan')->restrictOnDelete();
        });
        $this->check('phieu_su_co', [
            'ck_psc_ktv'  => "trang_thai_kanban IN ('cho_tiep_nhan','tu_choi','da_huy') OR ID_ky_thuat_vien IS NOT NULL",
            'ck_psc_anh'  => "trang_thai_kanban NOT IN ('cho_nghiem_thu','hoan_tat') OR anh_nghiem_thu IS NOT NULL",
            'ck_psc_tc'   => "trang_thai_kanban <> 'tu_choi' OR ly_do_tu_choi IS NOT NULL",
            'ck_psc_diem' => "diem_danh_gia IS NULL OR (diem_danh_gia BETWEEN 1 AND 5 AND trang_thai_kanban = 'hoan_tat')",
            'ck_psc_ht'   => "trang_thai_kanban <> 'hoan_tat' OR ngay_hoan_thanh IS NOT NULL",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('phieu_su_co');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
