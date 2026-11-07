<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng tai_khoan – Tài khoản đăng nhập và vai trò (Bảng 3.17 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tai_khoan', function (Blueprint $table) {
            $table->increments('ID_tai_khoan');
            $table->string('ten_dang_nhap', 50);
            $table->string('mat_khau_bam', 255);                 // bcrypt
            $table->string('ho_ten', 100);
            $table->string('email', 100)->nullable();
            $table->string('so_dien_thoai', 15)->nullable();
            $table->enum('vai_tro', ['chu_ho', 'khach_thue', 'le_tan', 'ke_toan', 'ke_toan_truong', 'dieu_phoi_vien', 'ky_thuat_vien', 'admin']);
            $table->tinyInteger('trang_thai')->default(1);
            $table->unsignedTinyInteger('so_lan_sai')->default(0);
            $table->dateTime('khoa_den')->nullable();             // khóa tạm 15 phút
            $table->tinyInteger('phai_doi_mat_khau')->default(0);
            $table->dateTime('ngay_tao')->useCurrent();

            $table->unique('ten_dang_nhap', 'uq_tk_ten');
            $table->unique('email', 'uq_tk_email');
            $table->unique('so_dien_thoai', 'uq_tk_sdt');
        });
        $this->check('tai_khoan', [
            'ck_tk_trang_thai' => 'trang_thai IN (0,1)',
            'ck_tk_doi_mk'     => 'phai_doi_mat_khau IN (0,1)',
            'ck_tk_so_lan_sai' => 'so_lan_sai <= 5',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tai_khoan');
    }

    /** Thêm ràng buộc CHECK (Schema Builder của Laravel chưa hỗ trợ CHECK nên dùng câu lệnh SQL) */
    private function check(string $table, array $checks): void
    {
        foreach ($checks as $name => $expr) {
            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expr)");
        }
    }
};
