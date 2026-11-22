<?php

namespace App\Actions\M08;

use App\Actions\M10\CompleteEnrollment;
use App\Actions\M10\FinalizeAttempt;
use App\Actions\M18\ExamTaking;
use App\Actions\M20\PracticeSessions;
use App\Actions\M22\GameSessions;
use App\Enums\M10\AttemptCloseReason;
use App\Enums\M10\AttemptStatus;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\TestBarrier;
use App\Support\Tx;

/**
 * QT-15 b13–16, A7–A9, E22 (NH-16/17; HL-5, HL-6). Khóa `users` (người thao tác, học viên theo id) → `enrollments`
 * → `quiz_attempts` (05 §1). Lượt `DANG_LAM` chốt ngay lý do `THU_HOI`; khôi phục giữ hạn và xét lại hoàn thành (CR-110).
 */
final class RevokeEnrollment
{
    public function revoke(User $actor, Enrollment $enrollment, string $reason, bool $confirmed): Enrollment
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainRejection('Nhập lý do thu hồi từ 1 đến 500 ký tự.', 'reason');
        }
        if (! $confirmed) {
            throw new DomainRejection('Hãy xác nhận thu hồi quyền học.', 'confirmed');
        }

        return Tx::run(function () use ($actor, $enrollment, $reason) {
            $enrollment = $this->lock($actor, $enrollment);
            if ($enrollment->revoked_at !== null) {
                throw new DomainRejection('Ghi danh đã bị thu hồi.');
            }
            $at = now()->floorMillisecond();
            $enrollment->update(['revoked_at' => $at, 'revoked_by_id' => $actor->id, 'revoked_reason' => $reason]);
            $attempts = QuizAttempt::query()->where('enrollment_id', $enrollment->id)->where('status', AttemptStatus::DangLam)
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($attempts as $attempt) {
                app(FinalizeAttempt::class)->underLock($enrollment, $attempt, $at, AttemptCloseReason::ThuHoi);
            }
            // CR-143: lượt thi kỳ chỉ định chốt THU_HOI khi không còn khóa chỉ định nào còn hạn.
            app(ExamTaking::class)->onAccessLost($enrollment->user()->firstOrFail(), $at);
            // E22 / CR-155: phiên luyện tập và game của đúng (học viên, khóa) chốt BO_DO cùng lần ghi thu hồi.
            app(PracticeSessions::class)->closeForEnrollment($enrollment, $at);
            app(GameSessions::class)->closeForEnrollment($enrollment, $at);
            Audit::log('ENROLLMENT_REVOKE', $actor, $enrollment, ['revoked' => false],
                ['revoked' => true, 'het_han_luc' => $enrollment->het_han_luc?->toIso8601String(), 'closed_attempts' => $attempts->count()], $reason);

            return $enrollment;
        });
    }

    public function restore(User $actor, Enrollment $enrollment): Enrollment
    {
        return Tx::run(function () use ($actor, $enrollment) {
            $enrollment = $this->lock($actor, $enrollment);
            if ($enrollment->revoked_at === null) {
                throw new DomainRejection('Ghi danh không ở trạng thái bị thu hồi.');
            }
            $enrollment->update(['revoked_at' => null, 'revoked_by_id' => null, 'revoked_reason' => null]);
            // CR-110: xét lại điều kiện hoàn thành ngay dưới cùng khóa ghi danh.
            app(CompleteEnrollment::class)->underLock($enrollment, now());
            Audit::log('ENROLLMENT_RESTORE', $actor, $enrollment, ['revoked' => true],
                ['revoked' => false, 'het_han_luc' => $enrollment->het_han_luc?->toIso8601String()]);

            return $enrollment;
        });
    }

    private function lock(User $actor, Enrollment $enrollment): Enrollment
    {
        $users = User::query()->whereKey([$actor->id, $enrollment->user_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        OrderLocks::academic($users->get($actor->id));
        $locked = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
        TestBarrier::reach('revoke-enrollment-locked');

        return $locked;
    }
}
