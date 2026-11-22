<?php

namespace App\Actions\M10;

use App\Enums\M10\AttemptStatus;
use App\Models\M04\Quiz;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttemptGrant;
use App\Models\User;
use App\Policies\M10\QuizPolicy;
use App\Queries\M10\QuizAttempts;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\CourseLocks;
use App\Support\TestBarrier;
use App\Support\Tx;

final class GrantExtraQuizAttempt
{
    public function handle(User $actor, Enrollment $enrollment, Quiz $quiz, string $reason): QuizAttemptGrant
    {
        return Tx::run(function () use ($actor, $enrollment, $quiz, $reason) {
            $actor = User::query()->whereKey($actor->id)->sharedLock()->firstOrFail();
            $course = CourseLocks::course($enrollment->course_id);
            $enrollment = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            TestBarrier::reach('grant-extra-quiz-attempt.locked');
            if ($enrollment->course_id !== $course->id || ! app(QuizPolicy::class)->grantExtraAttempt($actor, $quiz, $enrollment)) {
                throw DomainRejection::forbidden('Bạn không có quyền cấp thêm lượt cho quiz này.');
            }
            $reason = trim($reason);
            if ($reason === '' || mb_strlen($reason) > 500) {
                throw new DomainRejection('Nhập lý do cấp thêm lượt, tối đa 500 ký tự.', 'reason');
            }
            $attempts = QuizAttempts::for($enrollment, $quiz);
            $limit = QuizAttempts::limit($enrollment, $quiz);
            if ($quiz->lesson?->is_required !== true || $limit < 1 || (clone $attempts)->count() !== $limit
                || (clone $attempts)->where('status', AttemptStatus::DangLam)->exists() || $attempts->where('passed', true)->exists()) {
                throw new DomainRejection('Quiz không còn đủ điều kiện cấp thêm lượt.');
            }

            $grant = QuizAttemptGrant::query()->create([
                'enrollment_id' => $enrollment->id,
                'quiz_id' => $quiz->id,
                'extra_attempts' => 1,
                'reason' => $reason,
                'granted_by_id' => $actor->id,
                'created_at' => now(),
            ]);
            Audit::log('QUIZ_EXTRA_ATTEMPT_GRANT', $actor, $grant,
                ['max_attempts' => $limit],
                ['enrollment_id' => $enrollment->id, 'quiz_id' => $quiz->id, 'extra_attempts' => 1, 'max_attempts' => $limit + 1],
                $reason);

            return $grant;
        });
    }
}
