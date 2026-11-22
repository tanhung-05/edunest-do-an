<?php

namespace App\Support\M05;

use App\Enums\M05\LessonType;
use App\Models\M04\Quiz;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;

/**
 * Danh sách kiểm PB-3 (03 §3, QT-03 b7, CR-19/24/116/188). Trả mỗi lỗi một dòng có mã, bài và câu báo.
 */
final class DraftChecklist
{
    /**
     * @return list<array{code: string, lesson_id: int|null, message: string}>
     */
    public static function evaluate(CourseVersion $version): array
    {
        $lessons = Lesson::query()
            ->select('lessons.*')
            ->join('course_sections', 'course_sections.id', '=', 'lessons.course_section_id')
            ->where('lessons.course_version_id', $version->id)
            ->whereNull('lessons.removed_at')
            ->whereNull('course_sections.removed_at')
            ->orderBy('course_sections.position')->orderBy('course_sections.id')
            ->orderBy('lessons.position')->orderBy('lessons.id')
            ->get();

        $items = [];
        if ($lessons->isEmpty()) {
            return [['code' => 'E3', 'lesson_id' => null, 'message' => 'Khóa chưa có bài nào.']];
        }
        if ($lessons->where('is_required', true)->isEmpty()) {
            $items[] = ['code' => 'E3', 'lesson_id' => null, 'message' => 'Khóa chưa có bài bắt buộc nào.'];
        }

        $quizzes = Quiz::query()->whereIn('lesson_id', $lessons->pluck('id'))->withCount('items')->get()->keyBy('lesson_id');

        foreach ($lessons->values() as $i => $lesson) {
            $label = 'Bài '.($i + 1).' "'.$lesson->title.'"';
            if ($lesson->is_required) {
                $missing = match ($lesson->type) {
                    LessonType::Video => $lesson->video_path === null ? 'chưa có tệp MP4' : null,
                    LessonType::Pdf => $lesson->pdf_path === null ? 'chưa có tệp PDF' : null,
                    LessonType::BaiDoc => ! HtmlSanitizer::hasText($lesson->content) ? 'chưa có nội dung' : null,
                    LessonType::Quiz => null,
                };
                if ($missing !== null) {
                    $items[] = ['code' => 'E1', 'lesson_id' => $lesson->id, 'message' => "{$label}: {$missing}."];
                }
                if ($lesson->type === LessonType::Quiz) {
                    /** @var Quiz|null $quiz */
                    $quiz = $quizzes->get($lesson->id);
                    $lack = [];
                    if ($quiz === null || (int) $quiz->getAttribute('items_count') === 0) {
                        $lack[] = 'chưa có câu hỏi';
                    }
                    if ($quiz?->pass_percent === null) {
                        $lack[] = 'chưa đặt điểm đạt';
                    }
                    if ($quiz?->duration_minutes === null) {
                        $lack[] = 'chưa đặt thời lượng';
                    }
                    if ($quiz?->max_attempts === null) {
                        $lack[] = 'chưa đặt số lượt';
                    }
                    if ($lack !== []) {
                        $items[] = ['code' => 'E2', 'lesson_id' => $lesson->id, 'message' => "{$label}: ".implode(', ', $lack).'.'];
                    }
                }
            }
            // CR-116/A15: mọi video đã có tệp phải có thời lượng của đúng tệp đó.
            if ($lesson->type === LessonType::Video && $lesson->video_path !== null && $lesson->video_duration_ms === null) {
                $items[] = ['code' => 'A15', 'lesson_id' => $lesson->id, 'message' => "{$label}: chưa có thời lượng video."];
            }
        }

        // CR-24: tối đa một bài kiểm tra cuối, và phải là Quiz bắt buộc.
        $finals = $lessons->where('is_final_test', true)->values();
        foreach ($finals as $lesson) {
            if ($finals->count() > 1 || $lesson->type !== LessonType::Quiz || ! $lesson->is_required) {
                $pos = $lessons->search(fn (Lesson $l) => $l->id === $lesson->id);
                $items[] = ['code' => 'CR-24', 'lesson_id' => $lesson->id,
                    'message' => 'Bài '.((int) $pos + 1).' "'.$lesson->title.'": chỉ được một bài kiểm tra cuối và phải là Quiz bắt buộc.'];
            }
        }

        return $items;
    }
}
