<?php

namespace App\Support\M10;

use App\Enums\M09\Activity;
use App\Enums\M10\AttemptStatus;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M09\LearningAccess;
use App\Support\M09\LearningLocks;
use App\Support\TestBarrier;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Closure;

final class LockedQuizAttempt
{
    /**
     * @template T
     *
     * @param  Closure(User, Enrollment, QuizAttempt, Lesson, CarbonImmutable): (T|DomainRejection)  $work
     * @return T
     */
    public static function run(User $user, QuizAttempt $attempt, Closure $work): mixed
    {
        $result = Tx::run(function () use ($user, $attempt, $work) {
            $enrollment = $attempt->enrollment;
            if ($enrollment === null || $attempt->user_id !== $user->id) {
                throw DomainRejection::forbidden('Bạn không có quyền mở lượt này.');
            }
            [$user, $enrollment] = LearningLocks::student($user, $enrollment);
            $attempt = QuizAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            TestBarrier::reach('quiz-attempt.locked');
            $lesson = $attempt->quiz?->lesson;
            if (! LearningAccess::owns($user, $enrollment) || $lesson === null) {
                throw DomainRejection::forbidden('Bạn không có quyền mở lượt này.');
            }

            return $work($user, $enrollment, $attempt, $lesson, now()->floorMillisecond());
        });

        if ($result instanceof DomainRejection) {
            throw $result;
        }

        return $result;
    }

    public static function mayContinue(User $user, Enrollment $enrollment, QuizAttempt $attempt, Lesson $lesson, CarbonImmutable $at): bool
    {
        return $attempt->status === AttemptStatus::DangLam
            && LearningAccess::allows($user, $enrollment, Activity::ContinueQuizAttempt, $lesson, $attempt, $at);
    }
}
