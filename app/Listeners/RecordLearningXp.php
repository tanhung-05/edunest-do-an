<?php

namespace App\Listeners;

use App\Actions\M21\XpLedger;
use App\Enums\M21\XpSourceType;
use App\Events\M09\LessonCompleted;
use App\Events\M10\EnrollmentCompleted;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M09\LessonProgress;
use Carbon\CarbonImmutable;
use Illuminate\Events\Dispatcher;

/**
 * SK-08 / SK-10 → QT-23: hoàn thành bài (2 XP, khóa (học viên, khóa, lesson_key)) và hoàn thành khóa (50 XP) với giờ
 * hoàn thành gốc (CR-159). SK-09 (`AttemptFinalized`) không sinh XP (BR-XP-37): quiz đạt đã phát SK-08.
 * TV-14 quét lại bài hoàn thành thiếu nguồn nên lỗi ở đây không làm mất XP.
 */
final class RecordLearningXp
{
    public function __construct(private XpLedger $ledger) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(LessonCompleted::class, [self::class, 'lesson']);
        $events->listen(EnrollmentCompleted::class, [self::class, 'course']);
    }

    public function lesson(LessonCompleted $event): void
    {
        $progress = LessonProgress::query()->where('enrollment_id', $event->enrollmentId)->where('lesson_id', $event->lessonId)->first();
        if ($progress !== null) {
            $this->recordLesson($progress);
        }
    }

    public function course(EnrollmentCompleted $event): void
    {
        $enrollment = Enrollment::query()->find($event->enrollmentId);
        if ($enrollment === null || $enrollment->completed_at === null) {
            return;
        }
        $this->ledger->record(XpSourceType::HoanThanhKhoa, $event->completionEventId, $enrollment->user_id, $enrollment->completed_at,
            ['course_id' => $enrollment->course_id, 'enrollment_id' => $enrollment->id]);
    }

    public function recordLesson(LessonProgress $progress): void
    {
        if ($progress->completed_at === null) {
            return;
        }
        $enrollment = Enrollment::query()->find($progress->enrollment_id);
        $lessonKey = Lesson::query()->whereKey($progress->lesson_id)->value('lesson_key');
        if ($enrollment === null || $lessonKey === null) {
            return;
        }
        $this->ledger->record(XpSourceType::HoanThanhBai, (string) $progress->id, $enrollment->user_id, CarbonImmutable::parse($progress->completed_at),
            ['course_id' => $enrollment->course_id, 'lesson_key' => $lessonKey, 'lesson_id' => $progress->lesson_id]);
    }
}
