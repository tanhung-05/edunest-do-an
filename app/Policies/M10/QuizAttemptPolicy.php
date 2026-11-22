<?php

namespace App\Policies\M10;

use App\Enums\M09\Activity;
use App\Enums\M10\AttemptStatus;
use App\Models\M04\Quiz;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Queries\M10\QuizAttempts;
use App\Support\M03\AuthoringScope;
use App\Support\M09\LearningAccess;

final class QuizAttemptPolicy
{
    public function viewAny(User $user, Quiz $quiz, ?Enrollment $enrollment = null): bool
    {
        if ($enrollment === null) {
            $courseId = $quiz->lesson?->version?->course_id;

            return $courseId !== null && AuthoringScope::isCurrentAuthor($user, $courseId);
        }
        if (! LearningAccess::allows($user, $enrollment, Activity::ViewHistory)) {
            return false;
        }

        return ($quiz->lesson !== null && $quiz->course_version_id === $enrollment->course_version_id
            && LearningAccess::matches($enrollment, $quiz->lesson))
            || QuizAttempts::for($enrollment, $quiz)->where('status', '!=', AttemptStatus::DangLam)->exists();
    }

    public function view(User $user, QuizAttempt $attempt): bool
    {
        $enrollment = $attempt->enrollment;

        return $enrollment !== null && (($attempt->user_id === $user->id && LearningAccess::owns($user, $enrollment))
            || AuthoringScope::isCurrentAuthor($user, $enrollment->course_id));
    }

    public function continue(User $user, QuizAttempt $attempt): bool
    {
        $enrollment = $attempt->enrollment;

        return $enrollment !== null && LearningAccess::allows($user, $enrollment, Activity::ContinueQuizAttempt, $attempt->quiz?->lesson, $attempt);
    }

    public function save(User $user, QuizAttempt $attempt): bool
    {
        return $this->continue($user, $attempt);
    }

    public function submit(User $user, QuizAttempt $attempt): bool
    {
        return $this->continue($user, $attempt);
    }
}
