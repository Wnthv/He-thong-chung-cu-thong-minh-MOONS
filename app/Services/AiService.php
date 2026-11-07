<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\NguCanhAi;
use App\Support\Db;
use App\Support\Rbac;
use Normalizer;

/* ======================= TRỢ LÝ AI – RAG (UC-RES09, UC-ADM03) ======================= */
final class AiService
{
    const FALLBACK = 'Hệ thống AI hiện đang bảo trì hoặc bận, vui lòng liên hệ trực tiếp Lễ tân qua số Hotline tòa nhà 028 3838 6868.';

    public static function fold(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['đ' => 'd']);
        $s = Normalizer::normalize($s, Normalizer::FORM_D);
        return preg_replace('/[\x{0300}-\x{036f}]/u', '', $s);
    }
    private static function tokens(string $s): array
    {
        $stop = ['la', 'cua', 'toi', 'co', 'khong', 'the', 'nao', 'gi', 'bao', 'nhieu', 'va', 'cho', 'duoc', 'o', 'thi', 'may', 'ban', 'minh', 'a', 'oi', 'nhe', 'voi', 've', 'can', 'muon', 'hoi'];
        $w = preg_split('/[^a-z0-9]+/', self::fold($s), -1, PREG_SPLIT_NO_EMPTY);
        $w = array_values(array_filter($w, fn($x) => !in_array($x, $stop, true)));
        $bi = [];
        for ($i = 0; $i + 1 < count($w); $i++) $bi[] = $w[$i] . '_' . $w[$i + 1];
        return [$w, $bi];
    }
    /** Truy xuất (Retrieval): chấm điểm đoạn văn theo từ khóa và cụm 2 từ, lấy top-k */
    public static function retrieve(string $q, int $k = 3): array
    {
        [$qw, $qb] = self::tokens($q);
        if (!$qw) return [];
        $chunks = [];
        foreach (NguCanhAi::where('trang_thai', 'dang_su_dung')->get() as $d) {
            foreach (preg_split('/\n\s*\n/', $d['noi_dung_ngu_canh']) as $p) {
                [$pw, $pb] = self::tokens($d['tieu_de_tai_lieu'] . ' ' . $p);
                $hitW = count(array_intersect(array_unique($qw), $pw));
                $hitB = count(array_intersect($qb, $pb));
                $score = $hitW + 2 * $hitB;
                // Ngưỡng liên quan: khớp ≥ 50% từ khóa của câu hỏi hoặc khớp ít nhất 1 cụm 2 từ
                if ($hitB > 0 || $hitW / count(array_unique($qw)) >= 0.5) $chunks[] = ['score' => $score, 'title' => $d['tieu_de_tai_lieu'], 'text' => trim($p)];
            }
        }
        usort($chunks, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($chunks, 0, $k);
    }
    /** Câu hỏi về dữ liệu riêng (hóa đơn của tôi) – trả lời từ CSDL, KHÔNG gửi cho mô hình AI */
    private static function personal(string $q, ?array $me): ?array
    {
        $f = self::fold($q);
        if (!$me || !preg_match('/(hoa don|cuoc|tien).*(cua toi|nha toi|thang nay|can ho toi|cua minh)|(no|con no)/', $f)) return null;
        $inv = BillingService::visibleInvoices($me);
        if (!$inv) return ['answer' => 'Căn hộ của bạn chưa có hóa đơn nào được phát hành trong phạm vi bạn được xem.', 'source' => 'Dữ liệu hóa đơn căn hộ'];
        $h = $inv[0];
        $parts = Db::all("SELECT loai_cuoc, SUM(thanh_tien) t FROM chi_tiet_hoa_don WHERE ID_hoa_don=? GROUP BY loai_cuoc", [$h['ID_hoa_don']]);
        $names = ['dien' => 'điện', 'nuoc' => 'nước', 'quan_ly' => 'phí quản lý', 'gui_xe_o_to' => 'gửi ô tô', 'gui_xe_may' => 'gửi xe máy'];
        $detail = implode(', ', array_map(fn($p) => $names[$p['loai_cuoc']] . ' ' . money($p['t']), $parts));
        return ['answer' => sprintf('Hóa đơn %s của căn hộ %s: %s (%s). Trạng thái: %s.', mb_strtolower(ky_label($h['ky_cuoc'])),
            $me['ma_can_ho'], money($h['tong_tien']), $detail, mb_strtolower(lbl('hd', $h['trang_thai_thanh_toan']))),
            'source' => 'Dữ liệu hóa đơn căn hộ (không gửi tới AI)'];
    }

    public static function ask(string $q, ?array $me): array
    {
        $q = trim(mb_substr($q, 0, 500));
        if ($q === '') throw new BusinessException('Vui lòng nhập câu hỏi.');
        if ($p = self::personal($q, $me)) return $p + ['mode' => 'du_lieu_rieng'];
        $ctx = self::retrieve($q);
        if (!$ctx) return ['answer' => 'Tôi chưa tìm thấy quy định liên quan trong tài liệu của Ban Quản lý. Bạn vui lòng liên hệ Lễ tân để được hỗ trợ.',
            'source' => null, 'mode' => 'khong_tim_thay'];
        $source = implode('; ', array_unique(array_column($ctx, 'title')));
        $key = cfg('gemini')['api_key'];
        if ($key === '') {   // Chế độ tra cứu nội quy: trả nguyên văn đoạn liên quan nhất
            return ['answer' => $ctx[0]['text'], 'source' => $ctx[0]['title'], 'mode' => 'tra_cuu'];
        }
        $system = "Bạn là Moons Bot, trợ lý của Ban Quản lý chung cư Moons. Chỉ trả lời dựa trên NGỮ CẢNH được cung cấp, "
            . "văn phong lịch sự, ngắn gọn (tối đa 4 câu). Không bịa số liệu. Nếu ngữ cảnh không có thông tin, hãy nói chưa có thông tin và đề nghị liên hệ Lễ tân.";
        $context = implode("\n\n", array_map(fn($c) => "[{$c['title']}]\n{$c['text']}", $ctx));
        $body = json_encode(['systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => "NGỮ CẢNH:\n$context\n\nCÂU HỎI: $q"]]]],
            'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 400]], JSON_UNESCAPED_UNICODE);
        $g = cfg('gemini');
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$g['model']}:generateContent");
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $g['timeout'], CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key]]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $text = $res ? (json_decode($res, true)['candidates'][0]['content']['parts'][0]['text'] ?? null) : null;
        if ($code !== 200 || !$text) {
            AuditService::log('ai_loi', 'ngu_canh_ai', null, "HTTP $code");
            return ['answer' => self::FALLBACK, 'source' => null, 'mode' => 'du_phong'];
        }
        return ['answer' => trim($text), 'source' => $source, 'mode' => 'gemini'];
    }

    public static function saveDoc(?int $id, string $title, string $type, string $content): void
    {
        if (mb_strlen($title) < 5) throw new BusinessException('Tiêu đề tài liệu tối thiểu 5 ký tự.');
        if (mb_strlen($content) < 20) throw new BusinessException('Nội dung ngữ cảnh tối thiểu 20 ký tự.');
        $data = ['tieu_de_tai_lieu' => $title, 'loai_tai_lieu' => $type, 'noi_dung_ngu_canh' => $content, 'ID_nguoi_cap_nhat' => Rbac::id()];
        if ($id) {
            NguCanhAi::findOrFail($id)->update($data + ['trang_thai' => 'nhap', 'ngay_cap_nhat' => app_now()]);
        } else {
            $id0 = NguCanhAi::create($data)->ID_ngu_canh;
        }
        AuditService::log($id ? 'sua_ngu_canh' : 'them_ngu_canh', 'ngu_canh_ai', $id ?: $id0, $title);
    }
    /** "Đóng gói và cập nhật ngữ cảnh": đưa tài liệu nháp vào sử dụng (không huấn luyện lại mô hình) */
    public static function publish(): int
    {
        $n = NguCanhAi::where('trang_thai', 'nhap')->update(['trang_thai' => 'dang_su_dung']);
        AuditService::log('cap_nhat_ngu_canh_ai', 'ngu_canh_ai', null, "$n tài liệu");
        return $n;
    }
}
