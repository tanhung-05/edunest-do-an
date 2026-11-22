<?php

namespace App\Actions\M09;

use App\Enums\M05\LessonType;
use App\Enums\M09\Activity;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M09\LessonProgress;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M09\LearningAccess;
use App\Support\M09\LearningLocks;
use App\Support\TestBarrier;
use App\Support\Tx;

final class MarkLessonCompleted
{
    public function handle(User $user, Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        return Tx::run(function () use ($user, $enrollment, $lesson) {
            [$user, $enrollment] = LearningLocks::student($user, $enrollment);
            TestBarrier::reach('mark-lesson-completed.locked');
            $at = now();
            LearningAccess::authorize($user, $enrollment, Activity::CompleteLesson, $lesson, at: $at);
            if (! in_array($lesson->type, [LessonType::BaiDoc, LessonType::Pdf], true)) {
                throw new DomainRejection('Máy chủ xác nhận hoàn thành video và quiz.');
            }

            $progress = FinishLesson::progress($enrollment, $lesson, $at);
            FinishLesson::apply($enrollment, $progress, $at);

            return $progress;
        });
    }
}
