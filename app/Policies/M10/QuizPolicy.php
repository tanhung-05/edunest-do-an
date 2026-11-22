<?php

namespace App\Policies\M10;

use App\Enums\M01\RoleCode;
use App\Enums\M09\Activity;
use App\Enums\M10\AttemptStatus;
use App\Models\M04\Quiz;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Queries\M10\QuizAttempts;
use App\Support\M03\AuthoringScope;
use App\Support\M09\LearningAccess;

final class QuizPolicy
{
    public function startAttempt(User $user, Quiz $quiz, Enrollment $enrollment): bool
    {
        return $quiz->course_version_id === $enrollment->course_version_id
            && LearningAccess::allows($user, $enrollment, Activity::StartQuizAttempt, $quiz->lesson);
    }

    public function grantExtraAttempt(User $user, Quiz $quiz, Enrollment $enrollment): bool
    {
        return $quiz->lesson !== null && LearningAccess::matches($enrollment, $quiz->lesson)
            && $quiz->course_version_id === $enrollment->course_version_id
            && ($user->hasEffectiveRole(RoleCode::GiaoVu) || AuthoringScope::isCurrentAuthor($user, $enrollment->course_id));
    }

    public function viewAnswers(User $user, Quiz $quiz, ?QuizAttempt $attempt = null): bool
    {
        if ($attempt === null) {
            return app(QuizAttemptPolicy::class)->viewAny($user, $quiz);
        }
        $enrollment = $attempt->enrollment;
        if ($enrollment === null || $attempt->quiz_id !== $quiz->id) {
            return false;
        }
        if (AuthoringScope::isCurrentAuthor($user, $enrollment->course_id)) {
            return true;
        }
        if (! LearningAccess::owns($user, $enrollment) || $attempt->user_id !== $user->id || $attempt->status === AttemptStatus::DangLam) {
            return false;
        }
        $attempts = QuizAttempts::for($enrollment, $quiz);

        return (clone $attempts)->where('passed', true)->exists()
            || $attempts->where('status', '!=', AttemptStatus::DangLam)->count() >= QuizAttempts::limit($enrollment, $quiz);
    }
}
