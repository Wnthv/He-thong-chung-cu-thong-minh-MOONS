<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\BieuPhi;
use App\Models\DichVuTienIch;
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
        $docs = NguCanhAi::where('trang_thai', 'dang_su_dung')->get(['tieu_de_tai_lieu', 'noi_dung_ngu_canh'])->map->getAttributes()->all();
        // Tài liệu sinh tự động bị trừ 2 điểm để tài liệu do Ban Quản lý soạn được ưu tiên khi điểm ngang nhau
        foreach (array_merge($docs, array_map(fn($d) => $d + ['uu_tien' => -2], self::systemDocs())) as $d) {
            foreach (preg_split('/\n\s*\n/', $d['noi_dung_ngu_canh']) as $p) {
                [$pw, $pb] = self::tokens($d['tieu_de_tai_lieu'] . ' ' . $p);
                $hitW = count(array_intersect(array_unique($qw), $pw));
                $hitB = count(array_intersect($qb, $pb));
                $score = $hitW + 2 * $hitB + ($d['uu_tien'] ?? 0);
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
        $r = self::callGemini($system, "NGỮ CẢNH:\n$context\n\nCÂU HỎI: $q");
        if ($r['error']) {
            // Ghi rõ nguyên nhân để Admin chẩn đoán (xem nhật ký hoặc chạy: php artisan moons:ai-check)
            AuditService::log('ai_loi', 'ngu_canh_ai', null, $r['error']);
            return ['answer' => self::FALLBACK, 'source' => null, 'mode' => 'du_phong'];
        }
        $text = $r['text'];
        return ['answer' => trim($text), 'source' => $source, 'mode' => 'gemini'];
    }

    /**
     * Gọi Google Gemini (REST generateContent). Trả về ['text' => ..., 'error' => null|mô tả lỗi].
     * - Tắt "thinking" của dòng Flash 2.5 để trả lời nhanh (< 5 giây) và không bị hết maxOutputTokens trước khi sinh câu trả lời.
     * - Dùng bộ chứng chỉ gốc kèm dự án (resources/certs/cacert.pem) vì PHP trên WAMP/XAMPP thường chưa cấu hình curl.cainfo.
     */
    public static function callGemini(string $system, string $prompt): array
    {
        $g = cfg('gemini');
        $gen = ['temperature' => 0.2, 'maxOutputTokens' => 800];
        $send = function (array $gen, string $model) use ($g, $system, $prompt) {
            $body = json_encode(['systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => $gen], JSON_UNESCAPED_UNICODE);
            $ch = curl_init(rtrim($g['url'], '/') . "/models/$model:generateContent");
            $opt = [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $g['timeout'], CURLOPT_TIMEOUT => $g['timeout'],
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $g['api_key']]];
            $ca = resource_path('certs/cacert.pem');
            if (is_file($ca)) $opt[CURLOPT_CAINFO] = $ca;
            curl_setopt_array($ch, $opt);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            return [$res, $code, $err];
        };
        // Model chính + các model dự phòng (GEMINI_FALLBACK_MODELS, cách nhau dấu phẩy).
        // Khi Gemini quá tải (HTTP 503) hoặc hết hạn mức (429): thử lại 1 lần rồi chuyển sang model dự phòng.
        // Khi quá thời gian chờ: chuyển ngay sang model dự phòng (không thử lại cùng model).
        // Nếu chưa khai báo model dự phòng: tự chọn tối đa 2 model "flash" khác mà API key dùng được (ưu tiên flash-lite).
        $fallback = trim((string)$g['fallback_models']) !== '' ? explode(',', (string)$g['fallback_models']) : self::autoFallbackModels($g['model']);
        $models = array_values(array_unique(array_filter(array_map('trim', array_merge([$g['model']], $fallback)))));
        foreach ($models as $i => $model) {
            // Giảm thời gian "suy nghĩ" để trả lời nhanh: dòng 2.5 Flash dùng thinkingBudget = 0, dòng 3.x dùng thinkingLevel thấp nhất
            $genM = $gen;
            if (str_starts_with($model, 'gemini-2.5') && str_contains($model, 'flash')) $genM['thinkingConfig'] = ['thinkingBudget' => 0];
            elseif (preg_match('/^gemini-[3-9]/', $model)) $genM['thinkingConfig'] = ['thinkingLevel' => str_contains($model, 'flash') ? 'minimal' : 'low'];
            for ($try = 0; $try < 2; $try++) {
                [$res, $code, $curlErr] = $send($genM, $model);
                // Mô hình không nhận tham số thinkingConfig => gửi lại không kèm tham số này
                if ($code === 400 && isset($genM['thinkingConfig']) && stripos((string)$res, 'thinking') !== false) {
                    unset($genM['thinkingConfig']);
                    [$res, $code, $curlErr] = $send($genM, $model);
                }
                if (!in_array($code, [429, 500, 503], true)) break;
                if ($try === 0) usleep(800000);
            }
            if (!in_array($code, [0, 429, 500, 503], true) || $i === count($models) - 1) break;
        }
        if ($res === false) return ['text' => null, 'error' => "Không kết nối được Gemini (cURL): $curlErr"];
        $j = json_decode($res, true);
        if ($code !== 200) {
            $msg = $j['error']['message'] ?? mb_substr((string)$res, 0, 200);
            return ['text' => null, 'error' => "Gemini ($model) trả về HTTP $code: $msg"];
        }
        $parts = $j['candidates'][0]['content']['parts'] ?? [];
        $text = trim(implode('', array_map(fn($p) => empty($p['thought']) ? ($p['text'] ?? '') : '', $parts)));
        if ($text === '') {
            $why = $j['candidates'][0]['finishReason'] ?? ($j['promptFeedback']['blockReason'] ?? 'không rõ');
            return ['text' => null, 'error' => "Gemini không trả về nội dung (lý do: $why)"];
        }
        return ['text' => $text, 'error' => null, 'model' => $model];
    }

    /**
     * Tài liệu ngữ cảnh sinh TỰ ĐỘNG từ dữ liệu hệ thống (luôn khớp CSDL, không cần nhập tay):
     * biểu phí hiện hành (bảng bieu_phi – Kế toán trưởng cập nhật) và thông tin tiện ích (bảng dich_vu_tien_ich).
     */
    public static function systemDocs(): array
    {
        $f = BieuPhi::orderBy('ID_bieu_phi')->get()->keyBy('ma_phi');
        $p = [];
        $tiers = $f->filter(fn($x) => str_starts_with($x->ma_phi, 'DIEN_B'))->map(fn($x) => 'bậc ' . substr($x->ma_phi, -1) . ' ('
            . ($x->den_muc === null ? 'từ ' . $x->tu_muc . ' kWh trở lên' : $x->tu_muc . '–' . $x->den_muc . ' kWh') . ') giá ' . num($x->don_gia) . ' đồng/kWh');
        if ($tiers->isNotEmpty()) $p[] = 'Giá điện sinh hoạt (tiền điện, đơn giá điện) tính lũy tiến theo 6 bậc: ' . $tiers->implode('; ') . '.';
        $line = fn($code, $label) => isset($f[$code]) ? "$label là " . num($f[$code]->don_gia) . ' đồng/' . $f[$code]->don_vi : null;
        $p[] = implode('. ', array_filter([$line('NUOC', 'Giá nước sinh hoạt (tiền nước)'), $line('QUAN_LY', 'Phí quản lý (phí dịch vụ tòa nhà) theo diện tích căn hộ')])) . '.';
        $p[] = implode('. ', array_filter([$line('XE_O_TO', 'Phí gửi xe ô tô (giá vé xe ô tô tháng)'), $line('XE_MAY', 'Phí gửi xe máy (giá vé xe máy tháng)')]))
            . '. Phí gửi xe tính theo số xe đã được duyệt của căn hộ và cộng vào hóa đơn tháng.';
        $out = [['tieu_de_tai_lieu' => 'Biểu phí dịch vụ hiện hành', 'noi_dung_ngu_canh' => implode("\n\n", $p)]];
        $t = [];
        foreach (DichVuTienIch::orderBy('ID_tien_ich')->get() as $x) {
            $t[] = $x->ten_tien_ich . ': giờ mở cửa ' . substr($x->gio_mo_cua, 0, 5) . ' đến ' . substr($x->gio_dong_cua, 0, 5) . ', mỗi khung giờ '
                . ($x->thoi_luong_khung / 60) . ' tiếng, sức chứa tối đa ' . $x->suc_chua_toi_da . ' người'
                . ($x->doc_quyen ? ', mỗi khung giờ chỉ một căn hộ đặt' : ', nhiều căn hộ dùng chung trong một khung giờ')
                . ($x->han_muc_tuan ? ', mỗi căn hộ tối đa ' . $x->han_muc_tuan . ' lượt/tuần' : '')
                . ($x->trang_thai_hoat_dong === 'tam_dung' ? ' (đang tạm dừng hoạt động)' : '') . '. Đặt lịch trên ứng dụng ở mục Tiện ích.';
        }
        if ($t) $out[] = ['tieu_de_tai_lieu' => 'Thông tin tiện ích nội khu', 'noi_dung_ngu_canh' => implode("\n\n", $t)];
        return $out;
    }

    /** Danh sách model hỗ trợ generateContent của API key (lưu đệm 1 ngày) */
    public static function availableModels(): array
    {
        $g = cfg('gemini');
        return cache()->remember('gemini_models_' . md5($g['api_key'] . $g['url']), 86400, function () use ($g) {
            $ch = curl_init(rtrim($g['url'], '/') . '/models?pageSize=200');
            $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $g['api_key']]];
            if (is_file(resource_path('certs/cacert.pem'))) $opt[CURLOPT_CAINFO] = resource_path('certs/cacert.pem');
            curl_setopt_array($ch, $opt);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code !== 200) return [];
            $out = [];
            foreach (json_decode((string)$res, true)['models'] ?? [] as $m) {
                if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) $out[] = str_replace('models/', '', $m['name']);
            }
            return $out;
        });
    }

    private static function autoFallbackModels(string $main): array
    {
        try { $all = self::availableModels(); } catch (\Throwable $e) { return []; }
        $cand = array_filter($all, fn($m) => $m !== $main && str_starts_with($m, 'gemini') && str_contains($m, 'flash')
            && !preg_match('/image|tts|audio|live|preview|exp|embedding/', $m));
        usort($cand, fn($a, $b) => (str_contains($b, 'lite') <=> str_contains($a, 'lite')) ?: strnatcmp($b, $a));
        return array_slice(array_values($cand), 0, 2);
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
