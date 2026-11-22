<?php

namespace App\Policies\M09;

use App\Enums\M09\Activity;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Support\M09\LearningAccess;

final class LessonPolicy
{
    public function viewContent(User $user, Lesson $lesson, Enrollment $enrollment): bool
    {
        return LearningAccess::allows($user, $enrollment, Activity::ViewLesson, $lesson);
    }

    public function complete(User $user, Lesson $lesson, Enrollment $enrollment): bool
    {
        return LearningAccess::allows($user, $enrollment, Activity::CompleteLesson, $lesson);
    }

    public function recordWatchBeat(User $user, Lesson $lesson, Enrollment $enrollment): bool
    {
        return LearningAccess::allows($user, $enrollment, Activity::RecordWatchBeat, $lesson);
    }
}
