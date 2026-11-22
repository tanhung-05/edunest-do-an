<?php

namespace Database\Seeders;

use App\Enums\M04\QuestionDifficulty;
use App\Enums\M04\QuestionSource;
use App\Enums\M04\QuestionStatus;
use App\Enums\M04\QuestionType;
use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\M05\Lesson;
use App\Support\M25\FakeAiProvider;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu mẫu P8 M25 (QT-27a): hai câu Đúng/Sai nguồn AI ở trạng thái Nháp trong ngân hàng thường đầu tiên có bài đọc,
 * để Giáo viên thử Duyệt / Bỏ nháp / Khôi phục. Cờ AI và hạn mức lấy từ `Setting::DEFAULTS` (RoleSeeder). Chạy lại không tạo trùng.
 */
final class AiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Question::query()->where('source', QuestionSource::Ai->value)->exists()) {
            return;
        }
        $provider = new FakeAiProvider;
        foreach (QuestionBank::query()->where('is_confidential', false)->orderBy('id')->get() as $bank) {
            $lesson = Lesson::query()->whereNull('removed_at')->whereNotNull('content')
                ->whereIn('course_version_id', fn ($q) => $q->select('id')->from('course_versions')->where('course_id', $bank->course_id))
                ->orderByDesc('course_version_id')->orderBy('position')->first();
            $drafts = $lesson === null ? [] : $provider->suggestQuestions(($lesson->objectives ?? '').' '.$lesson->content, 2);
            if ($drafts === []) {
                continue;
            }
            foreach ($drafts as $d) {
                $q = Question::query()->create([
                    'question_bank_id' => $bank->id, 'type' => QuestionType::DungSai, 'difficulty' => QuestionDifficulty::TrungBinh,
                    'stem' => $d['stem'], 'points' => 1, 'explanation' => $d['explanation'],
                    'status' => QuestionStatus::Nhap, 'source' => QuestionSource::Ai,
                    'created_by_id' => $bank->created_by_id, 'updated_by_id' => $bank->created_by_id,
                ]);
                $q->options()->create(['content' => 'Đúng', 'is_correct' => $d['is_true'], 'position' => 1]);
                $q->options()->create(['content' => 'Sai', 'is_correct' => ! $d['is_true'], 'position' => 2]);
            }

            return;
        }
    }
}
