<?php

namespace App\Actions\M10;

use App\Actions\M09\FinishLesson;
use App\Enums\M08\EnrollmentValidity;
use App\Enums\M10\AttemptCloseReason;
use App\Enums\M10\AttemptStatus;
use App\Events\M10\AttemptFinalized;
use App\Models\M08\Enrollment;
use App\Models\M10\AttemptAnswer;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M09\LearningAccess;
use App\Support\M10\QuizScore;
use App\Support\TestBarrier;
use App\Support\Tx;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

final class FinalizeAttempt
{
    public function handle(QuizAttempt $attempt): QuizAttempt
    {
        return Tx::run(function () use ($attempt) {
            $enrollment = Enrollment::query()->whereKey($attempt->enrollment_id)->lockForUpdate()->firstOrFail();
            $current = QuizAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            TestBarrier::reach('finalize-attempt.locked');

            return $this->underLock($enrollment, $current, now()->floorMillisecond());
        });
    }

    public function underLock(Enrollment $enrollment, QuizAttempt $attempt, CarbonImmutable $at, ?AttemptCloseReason $requested = null): QuizAttempt
    {
        if ($attempt->status !== AttemptStatus::DangLam) {
            return $attempt;
        }

        $revoked = $enrollment->revoked_at;
        [$reason, $closed] = match (true) {
            $revoked !== null && $revoked->lte($at) && $revoked->lte($attempt->deadline_at) => [AttemptCloseReason::ThuHoi, $revoked],
            $at->gt($attempt->deadline_at) => [AttemptCloseReason::HetGio, $attempt->deadline_at],
            $requested !== null => [$requested, $at],
            default => [null, null],
        };
        if ($reason === null) {
            return $attempt;
        }

        $quiz = $attempt->quiz()->firstOrFail();
        $answers = AttemptAnswer::query()->where('quiz_attempt_id', $attempt->id)->get()->keyBy('quiz_question_id');
        $earned = BigDecimal::zero();
        $max = BigDecimal::zero();
        foreach ($quiz->items()->get() as $question) {
            $points = BigDecimal::of($question->points ?? '0');
            $max = $max->plus($points);
            $answer = $answers->get($question->id);
            if ($answer === null) {
                continue;
            }
            $correct = QuizScore::correct($question, $answer->answer);
            $answer->is_correct = $correct;
            $answer->points_awarded = $correct ? (string) $points : '0.00';
            $answer->save();
            if ($correct) {
                $earned = $earned->plus($points);
            }
        }

        $attempt->forceFill([
            'status' => $reason === AttemptCloseReason::HetGio ? AttemptStatus::HetGio : AttemptStatus::DaNop,
            'close_reason' => $reason,
            'closed_at' => $closed,
            'finalized_at' => $at,
            'score_earned' => (string) $earned,
            'score_max' => (string) $max,
            'passed' => $max->isPositive() && $earned->multipliedBy(100)->isGreaterThanOrEqualTo($max->multipliedBy($quiz->pass_percent ?? 100)),
        ])->save();

        $lesson = $quiz->lesson;
        if ($attempt->passed && $lesson !== null && LearningAccess::matches($enrollment, $lesson)
            && EnrollmentValidityChecker::for($enrollment, $at) !== EnrollmentValidity::DaThuHoi) {
            FinishLesson::apply($enrollment, FinishLesson::progress($enrollment, $lesson, $at), $at);
        }
        AttemptFinalized::dispatch($attempt->id);

        return $attempt;
    }

    public function forAccountLock(User $user): void
    {
        $enrollments = Enrollment::query()->where('user_id', $user->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $attempts = QuizAttempt::query()->where('user_id', $user->id)->whereIn('enrollment_id', $enrollments->keys())
            ->where('status', AttemptStatus::DangLam)->orderBy('id')->lockForUpdate()->get();

        foreach ($attempts as $attempt) {
            $enrollment = $enrollments->get($attempt->enrollment_id);
            if ($enrollment !== null) {
                $this->underLock($enrollment, $attempt, now()->floorMillisecond(), AttemptCloseReason::ThuHoi);
            }
        }
    }
}
