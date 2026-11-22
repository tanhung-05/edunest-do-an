<?php

namespace App\Actions\M09;

use App\Actions\M10\CompleteEnrollment;
use App\Events\M09\LessonCompleted;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M09\LessonProgress;
use Carbon\CarbonImmutable;

final class FinishLesson
{
    public static function progress(Enrollment $enrollment, Lesson $lesson, CarbonImmutable $at): LessonProgress
    {
        $progress = LessonProgress::query()->where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lesson->id)->lockForUpdate()->first();
        if ($enrollment->started_at === null) {
            $enrollment->started_at = $at;
            $enrollment->save();
        }

        return $progress ?? LessonProgress::query()->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'started_at' => $at,
        ]);
    }

    public static function apply(Enrollment $enrollment, LessonProgress $progress, CarbonImmutable $at): void
    {
        if ($progress->completed_at !== null) {
            return;
        }

        $progress->completed_at = $at;
        $progress->save();
        LessonCompleted::dispatch($enrollment->id, $progress->lesson_id);
        app(CompleteEnrollment::class)->underLock($enrollment, $at);
    }
}
