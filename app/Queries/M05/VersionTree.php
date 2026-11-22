<?php

namespace App\Queries\M05;

use App\Models\M04\Quiz;
use App\Models\M04\QuizQuestion;
use App\Models\M05\CourseReview;
use App\Models\M05\CourseSection;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\M05\LessonResource;

/** Cây chương/bài còn sống của một bản, dùng cho trình soạn, xem trước và xem duyệt. */
final class VersionTree
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function sections(CourseVersion $version): array
    {
        $sections = CourseSection::query()->where('course_version_id', $version->id)->whereNull('removed_at')
            ->orderBy('position')->orderBy('id')->get();
        $lessons = Lesson::query()->where('course_version_id', $version->id)->whereNull('removed_at')
            ->orderBy('position')->orderBy('id')->get()->groupBy('course_section_id');
        $lessonIds = $lessons->flatten()->pluck('id');
        $resources = LessonResource::query()->whereIn('lesson_id', $lessonIds)->whereNull('removed_at')->orderBy('position')->get()->groupBy('lesson_id');
        $quizzes = Quiz::query()->whereIn('lesson_id', $lessonIds)->get()->keyBy('lesson_id');
        $items = QuizQuestion::query()->with('question:id,stem,status')->whereIn('quiz_id', $quizzes->pluck('id'))->whereNull('removed_at')
            ->orderBy('position')->get()->groupBy('quiz_id');

        $numbers = [];
        foreach ($sections as $s) {
            foreach ($lessons->get($s->id, collect()) as $l) {
                $numbers[$l->id] = count($numbers) + 1;
            }
        }

        return $sections->map(fn (CourseSection $s) => [
            'id' => $s->id,
            'title' => $s->title,
            'lessons' => $lessons->get($s->id, collect())->map(function (Lesson $l) use ($numbers, $resources, $quizzes, $items) {
                /** @var Quiz|null $quiz */
                $quiz = $quizzes->get($l->id);

                return [
                    'id' => $l->id,
                    'number' => $numbers[$l->id],
                    'title' => $l->title,
                    'type' => $l->type->value,
                    'type_label' => $l->type->label(),
                    'objectives' => $l->objectives,
                    'content' => $l->content,
                    'is_required' => $l->is_required,
                    'is_preview' => $l->is_preview,
                    'is_final_test' => $l->is_final_test,
                    'has_video' => $l->video_path !== null,
                    'video_duration_ms' => $l->video_duration_ms,
                    'has_pdf' => $l->pdf_path !== null,
                    'resources' => $resources->get($l->id, collect())->map(fn (LessonResource $r) => [
                        'id' => $r->id, 'title' => $r->title, 'size_bytes' => $r->size_bytes,
                    ])->values()->all(),
                    'quiz' => $quiz === null ? null : [
                        'pass_percent' => $quiz->pass_percent,
                        'duration_minutes' => $quiz->duration_minutes,
                        'max_attempts' => $quiz->max_attempts,
                        'questions' => $items->get($quiz->id, collect())->map(fn (QuizQuestion $qq) => [
                            'id' => $qq->question_id,
                            'stem' => (string) ($qq->snapshot['stem'] ?? $qq->question->stem),
                            'snapshot' => $qq->snapshot,
                        ])->values()->all(),
                    ],
                ];
            })->values()->all(),
        ])->values()->all();
    }

    /**
     * Lịch sử duyệt kèm nhận xét theo bài.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function reviews(CourseVersion $version): array
    {
        return CourseReview::query()->with(['reviewer:id,name', 'comments'])->where('course_version_id', $version->id)
            ->orderBy('id')->get()->map(fn (CourseReview $r) => [
                'id' => $r->id,
                'result' => $r->result->value,
                'result_label' => $r->result->label(),
                'reason' => $r->reason,
                'reviewer' => $r->reviewer->name,
                'created_at' => $r->created_at->toIso8601String(),
                'comments' => $r->comments->map(fn ($c) => ['lesson_id' => $c->lesson_id, 'comment' => $c->comment])->values()->all(),
            ])->values()->all();
    }
}
