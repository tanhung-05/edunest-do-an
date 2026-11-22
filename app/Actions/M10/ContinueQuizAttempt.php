<?php

namespace App\Actions\M10;

use App\Enums\M09\Activity;
use App\Enums\M10\AttemptStatus;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M09\LearningAccess;
use App\Support\M10\LockedQuizAttempt;
use Carbon\CarbonImmutable;

final class ContinueQuizAttempt
{
    public function __construct(private FinalizeAttempt $finalize) {}

    public function handle(User $user, QuizAttempt $attempt): QuizAttempt
    {
        return LockedQuizAttempt::run($user, $attempt, fn (User $user, Enrollment $enrollment, QuizAttempt $attempt, Lesson $lesson, CarbonImmutable $at) => $this->underLock($user, $enrollment, $attempt, $lesson, $at));
    }

    public function underLock(User $user, Enrollment $enrollment, QuizAttempt $attempt, Lesson $lesson, CarbonImmutable $at): QuizAttempt|DomainRejection
    {
        $attempt = $this->finalize->underLock($enrollment, $attempt, $at);
        if ($attempt->status !== AttemptStatus::DangLam
            || LearningAccess::allows($user, $enrollment, Activity::ContinueQuizAttempt, $lesson, $attempt, $at)) {
            return $attempt;
        }

        return DomainRejection::forbidden('Bạn không có quyền tiếp tục lượt này.');
    }
}
