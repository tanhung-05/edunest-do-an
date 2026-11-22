<?php

namespace App\Queries\M10;

use App\Enums\M10\AttemptStatus;
use App\Models\M04\Quiz;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use App\Models\M10\QuizAttemptGrant;
use App\Support\M10\QuizScore;
use Brick\Math\BigRational;
use Illuminate\Database\Eloquent\Builder;

final class QuizAttempts
{
    /** @return Builder<QuizAttempt> */
    public static function for(Enrollment $enrollment, Quiz $quiz): Builder
    {
        return QuizAttempt::query()->where('enrollment_id', $enrollment->id)->where('quiz_id', $quiz->id);
    }

    public static function limit(Enrollment $enrollment, Quiz $quiz): int
    {
        return ($quiz->max_attempts ?? 0) + (int) QuizAttemptGrant::query()
            ->where('enrollment_id', $enrollment->id)->where('quiz_id', $quiz->id)->sum('extra_attempts');
    }

    public static function best(Enrollment $enrollment, Quiz $quiz): ?BigRational
    {
        $best = null;
        foreach (self::for($enrollment, $quiz)->where('status', '!=', AttemptStatus::DangLam)->cursor() as $attempt) {
            $ratio = QuizScore::ratio($attempt);
            if ($ratio !== null && ($best === null || $ratio->isGreaterThan($best))) {
                $best = $ratio;
            }
        }

        return $best;
    }
}
