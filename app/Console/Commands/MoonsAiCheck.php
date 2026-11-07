<?php

namespace App\Console\Commands;

use App\Services\AiService;
use Illuminate\Console\Command;

/**
 * Kiểm tra kết nối Trợ lý AI với Google Gemini và in ra nguyên nhân lỗi (nếu có).
 *   php artisan moons:ai-check
 */
class MoonsAiCheck extends Command
{
    protected $signature = 'moons:ai-check {cau_hoi? : Câu hỏi thử} {--models : Liệt kê các model Gemini mà API key được dùng}';

    protected $description = 'Kiểm tra cấu hình và kết nối Google Gemini của Trợ lý AI';

    public function handle(): int
    {
        $g = cfg('gemini');
        $key = $g['api_key'];
        $this->line('Model      : ' . $g['model']);
        $auto = $g['fallback_models'] === '' && $g['api_key'] !== '' ? (fn() => implode(', ', (new \ReflectionMethod(AiService::class, 'autoFallbackModels'))->invoke(null, $g['model'])))() : '';
        $this->line('Dự phòng   : ' . ($g['fallback_models'] ?: ($auto ? "$auto (tự chọn)" : '(không)')));
        $this->line('Timeout    : ' . $g['timeout'] . ' giây');
        $this->line('API key    : ' . ($key === '' ? '(trống – AI chạy chế độ tra cứu nội quy)' : substr($key, 0, 6) . '…' . substr($key, -4) . ' (' . strlen($key) . ' ký tự)'));
        $this->line('PHP cURL   : ' . (function_exists('curl_init') ? 'có' : 'THIẾU – bật extension=curl trong php.ini'));
        $this->line('Chứng chỉ  : ' . (is_file(resource_path('certs/cacert.pem')) ? 'resources/certs/cacert.pem' : 'không có'));
        if ($key === '') {
            $this->warn('Chưa có GEMINI_API_KEY trong tệp .env (sau khi sửa .env hãy chạy: php artisan config:clear).');
            return 1;
        }

        if ($this->option('models')) return $this->listModels($g);

        $q = $this->argument('cau_hoi') ?: 'Giờ mở cửa hồ bơi?';
        $ctx = AiService::retrieve($q);
        $this->line("\nCâu hỏi thử: $q");
        $this->line('Đoạn ngữ cảnh tìm được: ' . count($ctx) . ($ctx ? ' (' . implode('; ', array_unique(array_column($ctx, 'title'))) . ')' : ''));

        $t = microtime(true);
        $r = AiService::callGemini('Trả lời ngắn gọn bằng tiếng Việt.', $ctx
            ? "NGỮ CẢNH:\n" . implode("\n\n", array_column($ctx, 'text')) . "\n\nCÂU HỎI: $q" : $q);
        $ms = round((microtime(true) - $t) * 1000);

        if ($r['error']) {
            $this->error("LỖI sau {$ms} ms: {$r['error']}");
            $this->line("\nGợi ý xử lý:");
            $e = $r['error'];
            if (str_contains($e, 'SSL') || str_contains($e, 'certificate')) $this->line('- Lỗi chứng chỉ SSL: kiểm tra tệp resources/certs/cacert.pem có tồn tại.');
            if (str_contains($e, 'timed out') || str_contains($e, 'Timeout')) $this->line('- Quá thời gian: tăng GEMINI_TIMEOUT=15 trong .env (mạng chậm).');
            if (str_contains($e, 'API key') || str_contains($e, 'HTTP 400') || str_contains($e, 'HTTP 403')) $this->line('- API key sai/chưa bật: tạo lại key tại https://aistudio.google.com/apikey và dán đúng vào .env.');
            if (str_contains($e, 'HTTP 404')) $this->line('- Tên model không còn hỗ trợ: đổi GEMINI_MODEL trong .env sang tên model mà thông báo lỗi ở trên gợi ý.');
            if (str_contains($e, 'MAX_TOKENS') || str_contains($e, 'SAFETY')) $this->line('- Mô hình không sinh được câu trả lời: thử GEMINI_MODEL=gemini-2.5-flash-lite.');
            if (str_contains($e, 'HTTP 503') || str_contains($e, 'HTTP 500')) $this->line('- Máy chủ Gemini đang quá tải (lỗi tạm thời phía Google): đợi vài phút rồi thử lại, hoặc khai báo model dự phòng GEMINI_FALLBACK_MODELS trong .env.');
            if (str_contains($e, 'HTTP 429')) $this->line('- Hết hạn mức miễn phí: đợi một lúc hoặc dùng key khác.');
            if (str_contains($e, 'Could not resolve') || str_contains($e, 'connect')) $this->line('- Không có mạng hoặc tường lửa chặn generativelanguage.googleapis.com.');
            $this->line('Sau khi sửa .env, chạy: php artisan config:clear rồi chạy lại lệnh này.');
            return 1;
        }
        $this->info("THÀNH CÔNG sau {$ms} ms (model {$r['model']}). Gemini trả lời:");
        $this->line($r['text']);
        return 0;
    }

    /** In danh sách model hỗ trợ generateContent để chọn GEMINI_MODEL / GEMINI_FALLBACK_MODELS */
    private function listModels(array $g): int
    {
        $ch = curl_init(rtrim($g['url'], '/') . '/models?pageSize=200');
        $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $g['api_key']]];
        if (is_file(resource_path('certs/cacert.pem'))) $opt[CURLOPT_CAINFO] = resource_path('certs/cacert.pem');
        curl_setopt_array($ch, $opt);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $j = json_decode((string)$res, true);
        if ($code !== 200) { $this->error("HTTP $code: " . ($j['error']['message'] ?? $res)); return 1; }
        $this->line("\nCác model dùng được (generateContent):");
        foreach ($j['models'] ?? [] as $m) {
            if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) {
                $this->line('  ' . str_replace('models/', '', $m['name']) . '  –  ' . ($m['displayName'] ?? ''));
            }
        }
        return 0;
    }
}
