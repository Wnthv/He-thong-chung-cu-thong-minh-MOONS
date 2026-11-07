<?php

namespace App\Console\Commands;

use App\Models\NguCanhAi;
use App\Models\TaiKhoan;
use Illuminate\Console\Command;

/**
 * Nạp / cập nhật bộ tài liệu ngữ cảnh mẫu (database/seeders/ai_docs.php) vào bảng ngu_canh_ai
 * của CSDL đang dùng, KHÔNG xóa dữ liệu khác:
 *   php artisan moons:ai-docs
 * Tài liệu đã có (trùng tiêu đề) được cập nhật nội dung; tài liệu chưa có được thêm mới.
 */
class MoonsAiDocs extends Command
{
    protected $signature = 'moons:ai-docs {--dry : Chỉ liệt kê, không ghi vào CSDL}';

    protected $description = 'Nạp bộ tài liệu ngữ cảnh mẫu cho Trợ lý AI vào bảng ngu_canh_ai';

    public function handle(): int
    {
        $docs = require database_path('seeders/ai_docs.php');
        $users = TaiKhoan::pluck('ID_tai_khoan', 'ten_dang_nhap')->all();
        $fallback = $users['trilq'] ?? TaiKhoan::where('vai_tro', 'admin')->value('ID_tai_khoan');
        $added = $updated = $same = 0;

        foreach ($docs as [$title, $type, $content, $by]) {
            $row = NguCanhAi::where('tieu_de_tai_lieu', $title)->first();
            $data = ['loai_tai_lieu' => $type, 'noi_dung_ngu_canh' => $content, 'trang_thai' => 'dang_su_dung',
                     'ngay_cap_nhat' => now()->format('Y-m-d H:i:s'), 'ID_nguoi_cap_nhat' => $users[$by] ?? $fallback];
            if ($row && $row->noi_dung_ngu_canh === $content && $row->trang_thai === 'dang_su_dung') { $same++; continue; }
            $this->line(($row ? '  ~ cập nhật: ' : '  + thêm mới: ') . $title);
            if ($this->option('dry')) continue;
            if ($row) { $row->fill($data)->save(); $updated++; }
            else { NguCanhAi::create($data + ['tieu_de_tai_lieu' => $title]); $added++; }
        }
        $this->info("Xong: thêm $added, cập nhật $updated, giữ nguyên $same tài liệu. Tổng đang dùng: "
            . NguCanhAi::where('trang_thai', 'dang_su_dung')->count());
        return 0;
    }
}
