<?php

namespace App\Actions\M10;

use App\Enums\M10\AttemptCloseReason;
use App\Enums\M10\AttemptStatus;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M10\LockedQuizAttempt;
use Carbon\CarbonImmutable;

final class SubmitQuizAttempt
{
    public function __construct(private FinalizeAttempt $finalize) {}

    public function handle(User $user, QuizAttempt $attempt): QuizAttempt
    {
        return LockedQuizAttempt::run($user, $attempt, function (User $user, Enrollment $enrollment, QuizAttempt $attempt, Lesson $lesson, CarbonImmutable $at) {
            $this->finalize->underLock($enrollment, $attempt, $at);
            if ($attempt->status === AttemptStatus::HetGio) {
                return new DomainRejection('Lượt đã hết giờ.');
            }
            if ($attempt->status !== AttemptStatus::DangLam) {
                return $attempt;
            }
            if (! LockedQuizAttempt::mayContinue($user, $enrollment, $attempt, $lesson, $at)) {
                throw DomainRejection::forbidden('Bạn không có quyền nộp lượt này.');
            }

            return $this->finalize->underLock($enrollment, $attempt, $at, AttemptCloseReason::Nop);
        });
    }
}
