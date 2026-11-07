<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng nhat_ky_thao_tac – Nhật ký thao tác chỉ ghi thêm (Bảng 3.15 trong báo cáo).
 * Mọi khóa ngoại dùng ON DELETE RESTRICT; tên ràng buộc (uq_/fk_/ck_) đúng như thiết kế CSDL mục 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nhat_ky_thao_tac', function (Blueprint $table) {
            $table->bigIncrements('ID_nhat_ky');
            $table->string('hanh_dong', 50);
            $table->string('doi_tuong', 50);
            $table->string('ID_doi_tuong', 40)->nullable();
            $table->string('noi_dung', 1000)->nullable();
            $table->string('dia_chi_ip', 45)->nullable();
            $table->dateTime('thoi_gian')->useCurrent();
            $table->unsignedInteger('ID_tai_khoan')->nullable();

            $table->foreign('ID_tai_khoan', 'fk_nk_tk2')->references('ID_tai_khoan')->on('tai_khoan')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nhat_ky_thao_tac');
    }
};
