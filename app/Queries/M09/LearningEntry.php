<?php

namespace App\Queries\M09;

use App\Enums\M08\EnrollmentValidity;
use App\Enums\M09\Activity;
use App\Models\M03\Course;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M09\LessonProgress;
use App\Models\User;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M09\LearningAccess;

final class LearningEntry
{
    public static function enrollment(User $user, Course $course): Enrollment
    {
        $enrollment = Enrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->first();
        abort_if($enrollment === null, 403, 'Bạn không có ghi danh cho khóa này.');
        $enrollment->setRelation('course', $course);

        return $enrollment;
    }

    /** @return array{lesson_id: int, url: string, position_ms: int|null}|null */
    public static function for(User $user, Enrollment $enrollment): ?array
    {
        if (! LearningAccess::owns($user, $enrollment) || EnrollmentValidityChecker::for($enrollment) !== EnrollmentValidity::ConHan) {
            return null;
        }
        $progress = LessonProgress::query()->where('enrollment_id', $enrollment->id)->get()->keyBy('lesson_id');
        $open = LearningAccess::lessons($enrollment)->filter(fn (Lesson $lesson) => $progress->get($lesson->id)?->completed_at === null
            && LearningAccess::allows($user, $enrollment, Activity::ViewLesson, $lesson));
        $lesson = $open->filter(fn (Lesson $lesson) => $progress->get($lesson->id)?->last_viewed_at !== null)
            ->sortByDesc(fn (Lesson $lesson) => $progress->get($lesson->id)?->last_viewed_at)->first() ?? $open->first();
        if ($lesson === null) {
            return null;
        }

        return [
            'lesson_id' => $lesson->id,
            'url' => route('student.lessons.show', ['course' => $enrollment->course_id, 'lesson' => $lesson->id]),
            'position_ms' => $progress->get($lesson->id)?->last_position_ms,
        ];
    }

    /** P10 (UAT): khóa đã học xong hết thì dẫn về bài đầu tiên còn xem được để ôn lại; hết hạn hoặc thu hồi thì không có. */
    public static function review(User $user, Enrollment $enrollment): ?string
    {
        if (! LearningAccess::owns($user, $enrollment) || EnrollmentValidityChecker::for($enrollment) !== EnrollmentValidity::ConHan) {
            return null;
        }
        $lesson = LearningAccess::lessons($enrollment)
            ->first(fn (Lesson $lesson) => LearningAccess::allows($user, $enrollment, Activity::ViewLesson, $lesson));

        return $lesson === null ? null : route('student.lessons.show', ['course' => $enrollment->course_id, 'lesson' => $lesson->id]);
    }
}
