<?php

namespace App\Console\Commands;

use App\Support\M25\GeminiAiProvider;
use Illuminate\Console\Command;
use Throwable;

/** P10 (CR-255): kiểm cấu hình AI thật sau khi dán khóa vào `.env`. Không in khóa. */
class AiCheck extends Command
{
    protected $signature = 'edunest:ai-check';

    protected $description = 'Kiểm khóa Gemini: liệt kê mô hình dùng được và thử một câu tư vấn';

    public function handle(): int
    {
        $key = (string) config('services.gemini.key');
        if ($key === '') {
            $this->warn('Chưa có GEMINI_API_KEY trong .env: ứng dụng đang dùng bộ giả lập.');

            return self::FAILURE;
        }
        $model = (string) config('services.gemini.model');
        $ai = new GeminiAiProvider($key, $model, rtrim((string) config('services.gemini.endpoint'), '/'), (int) config('services.gemini.timeout', 30));
        try {
            $models = array_values(array_filter($ai->models(), fn (string $m) => str_contains($m, 'gemini')));
            $this->info('Khóa hợp lệ. Một số mô hình dùng được: '.implode(', ', array_slice($models, 0, 8)));
            if (! in_array('models/'.$model, $models, true)) {
                $this->warn("Không thấy mô hình {$model} trong danh sách; đặt GEMINI_MODEL thành một tên ở trên (bỏ tiền tố models/).");
            }
            $out = $ai->adviseCourses('Em lớp 12 muốn ôn hóa thi đại học', [
                ['id' => 1, 'title' => 'Hóa 12 luyện thi', 'summary' => 'Ôn tập hóa học lớp 12 thi tốt nghiệp'],
                ['id' => 2, 'title' => 'Tiếng Anh giao tiếp', 'summary' => 'Giao tiếp cơ bản'],
            ]);
            $this->info('Thử tư vấn: '.$out['answer'].' (khóa gợi ý: '.implode(', ', $out['course_ids']).')');
        } catch (Throwable $e) {
            $this->error('Gọi Gemini lỗi: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
