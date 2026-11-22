<?php

namespace App\Actions\M10;

use App\Enums\M08\EnrollmentValidity;
use App\Enums\M08\LearningStatus;
use App\Models\M08\Enrollment;
use App\Models\M09\LessonProgress;
use App\Models\M10\Certificate;
use App\Queries\M10\QuizAttempts;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M09\LearningAccess;
use App\Support\TestBarrier;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CompleteEnrollment
{
    public function handle(Enrollment $enrollment): ?Certificate
    {
        return Tx::run(function () use ($enrollment) {
            $enrollment = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

            return $this->underLock($enrollment, now());
        });
    }

    public function underLock(Enrollment $enrollment, CarbonImmutable $at): ?Certificate
    {
        TestBarrier::reach('complete-enrollment.locked');
        if ($enrollment->learning_status !== LearningStatus::HoanThanh) {
            if (EnrollmentValidityChecker::for($enrollment, $at) === EnrollmentValidity::DaThuHoi || ! $this->eligible($enrollment)) {
                return null;
            }

            $enrollment->learning_status = LearningStatus::HoanThanh;
            $enrollment->completed_at = $at;
            $enrollment->save();
        }
        $existing = Certificate::query()->where('enrollment_id', $enrollment->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $issued = ($enrollment->completed_at ?? $at)->setTimezone('Asia/Ho_Chi_Minh');
        for ($try = 1; ; $try++) {
            try {
                $certificate = Certificate::query()->create([
                    'enrollment_id' => $enrollment->id,
                    'certificate_number' => $issued->format('Y').'-'.self::suffix(),
                    'holder_name' => $enrollment->user()->firstOrFail()->name,
                    'course_title' => $enrollment->course()->firstOrFail()->title,
                    'issued_on' => $issued->toDateString(),
                    'completion_event_id' => 'HT-'.$enrollment->id,
                ]);
                break;
            } catch (QueryException $e) {
                if ($try >= 3 || Tx::code($e) !== 1062 || ! str_contains($e->getMessage(), 'certificates_certificate_number_unique')) {
                    throw $e;
                }
            }
        }

        DB::afterCommit(function () use ($certificate): void {
            try {
                app(RecoverEnrollmentCompletions::class)->recover($certificate->id);
            } catch (\Throwable $failure) {
                report($failure);
            }
        });

        return $certificate;
    }

    private function eligible(Enrollment $enrollment): bool
    {
        $completed = LessonProgress::query()->where('enrollment_id', $enrollment->id)
            ->whereNotNull('completed_at')->pluck('lesson_id')->flip();

        foreach (LearningAccess::lessons($enrollment)->loadMissing('quiz') as $lesson) {
            if (($lesson->is_required || $lesson->is_final_test) && ! $completed->has($lesson->id)) {
                return false;
            }
            if ($lesson->is_final_test) {
                $quiz = $lesson->quiz;
                if ($quiz === null || $quiz->pass_percent === null
                    || ! QuizAttempts::best($enrollment, $quiz)?->multipliedBy(100)->isGreaterThanOrEqualTo($quiz->pass_percent)) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function suffix(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $value = '';
        for ($i = 0; $i < 8; $i++) {
            $value .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $value;
    }
}
