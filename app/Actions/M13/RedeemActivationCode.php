<?php

namespace App\Actions\M13;

use App\Enums\M03\SaleStatus;
use App\Enums\M05\CourseVersionStatus;
use App\Enums\M08\EnrollmentSource;
use App\Enums\M08\LearningStatus;
use App\Enums\M13\ActivationCodeStatus;
use App\Events\M08\EnrollmentGranted;
use App\Models\M03\Course;
use App\Models\M08\Enrollment;
use App\Models\M13\ActivationAttemptWindow;
use App\Models\M13\ActivationCode;
use App\Models\User;
use App\Queries\M07\OpenOrders;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M12\Commerce;
use App\Support\TestBarrier;
use App\Support\Tx;
use Carbon\CarbonImmutable;

/**
 * QT-14 b4–10, A2–A12, E19 (NH-14; MK-2 cùng GH-1/HL-1 hoặc HL-4).
 * Khóa: `users` → (cửa sổ nhập sai) → `courses` → `activation_codes` → `enrollments` (05 §1).
 * Từ chối nghiệp vụ trả về sau commit để lần sai vẫn được ghi (CR-129).
 */
final class RedeemActivationCode
{
    public const MAX_FAILURES = 5;

    public const WINDOW_SECONDS = 900;

    public function handle(User $actor, string $raw): Enrollment
    {
        $result = Tx::run(fn () => $this->redeem($actor, Commerce::normalizeCode($raw)));
        if ($result instanceof DomainRejection) {
            throw $result;
        }

        return $result;
    }

    private function redeem(User $actor, string $code): Enrollment|DomainRejection
    {
        $student = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        OrderLocks::student($student);
        if (! $student->hasVerifiedEmail()) {
            throw DomainRejection::forbidden('Hãy xác minh email trước khi kích hoạt mã.');
        }
        $now = now();
        $window = ActivationAttemptWindow::query()->where('user_id', $student->id)->lockForUpdate()->first()
            ?? ActivationAttemptWindow::query()->create(['user_id' => $student->id, 'failures' => 0]);
        if ($window->locked_until !== null) {
            if ($now->lt($window->locked_until)) {
                return new DomainRejection('Bạn đã nhập sai quá nhiều lần. Thử lại sau '
                    .$window->locked_until->setTimezone(Commerce::TZ)->format('H:i').'.', 'code');
            }
            $window->update(['locked_until' => null, 'failures' => 0, 'window_started_at' => null]);
        }

        $found = $code === '' ? null : ActivationCode::query()->where('code', $code)->first();
        if ($found === null) {
            return $this->recordFailure($window, $now);
        }
        $batch = $found->batch()->firstOrFail();
        $course = Course::query()->whereKey($batch->course_id)->sharedLock()->firstOrFail();
        $row = ActivationCode::query()->whereKey($found->id)->lockForUpdate()->firstOrFail();
        TestBarrier::reach('redeem-code-locked');

        $reject = match (true) {
            $row->status === ActivationCodeStatus::DaDung => 'Mã đã được sử dụng.',
            $row->status === ActivationCodeStatus::DaVoHieu => 'Mã đã bị vô hiệu.',
            $row->status === ActivationCodeStatus::HetHan, $now->gt($batch->redeem_until) => 'Mã đã hết hạn đổi.',
            $course->sale_status === SaleStatus::ChuaBan || $course->current_version_id === null => 'Khóa học của mã chưa mở.',
            default => null,
        };
        if ($reject !== null) {
            return new DomainRejection($reject, 'code');
        }
        $open = OpenOrders::forCourses($student->id, [$course->id], true)->orderBy('id')->first();
        if ($open !== null && ($open->status->value !== 'CHO_THANH_TOAN' || $now->lte($open->expires_at))) {
            return new DomainRejection('Bạn đang có đơn '.$open->public_code.' cho khóa này. Hãy hủy hoặc hoàn tất đơn đó trước.', 'code');
        }

        $enrollment = Enrollment::query()->where('user_id', $student->id)->where('course_id', $course->id)->lockForUpdate()->first();
        if ($enrollment !== null) {
            $reject = match (true) {
                $enrollment->revoked_at !== null => 'Quyền học của khóa đã bị thu hồi. Hãy liên hệ giáo vụ.',
                $enrollment->het_han_luc === null, $now->lt($enrollment->het_han_luc) => 'Bạn đang có quyền học còn hạn cho khóa này.',
                $batch->access_months === null => 'Mã vĩnh viễn không dùng để cộng hạn ghi danh có thời hạn.',
                default => null,
            };
            if ($reject !== null) {
                return new DomainRejection($reject, 'code');
            }
            $before = $enrollment->het_han_luc;
            $enrollment->update(['het_han_luc' => $now->addMonthsNoOverflow($batch->access_months)]);
            Audit::log('ENROLLMENT_EXTEND_BY_CODE', $student, $enrollment, ['het_han_luc' => $before?->toIso8601String()],
                ['het_han_luc' => $enrollment->het_han_luc?->toIso8601String(), 'code_id' => $row->id]);
        } else {
            $version = $course->currentVersion()->where('status', CourseVersionStatus::DaCongBo)->first();
            if ($version === null) {
                return new DomainRejection('Khóa học của mã chưa có bản công bố.', 'code');
            }
            $enrollment = Enrollment::query()->create([
                'user_id' => $student->id, 'course_id' => $course->id, 'course_version_id' => $version->id,
                'learning_status' => LearningStatus::DangHoc, 'source' => EnrollmentSource::MaKichHoat,
                'activation_code_id' => $row->id, 'granted_at' => $now,
                'het_han_luc' => $batch->access_months === null ? null : $now->addMonthsNoOverflow($batch->access_months),
            ]);
            EnrollmentGranted::dispatch($enrollment);
        }
        $row->update(['status' => ActivationCodeStatus::DaDung, 'redeemed_by_id' => $student->id, 'redeemed_at' => $now, 'enrollment_id' => $enrollment->id]);

        return $enrollment;
    }

    /** CR-129: cửa sổ cố định 900 giây từ lần sai đầu; lần thứ 5 khóa 15 phút. */
    private function recordFailure(ActivationAttemptWindow $window, CarbonImmutable $now): DomainRejection
    {
        $fresh = $window->window_started_at === null || $now->gt($window->window_started_at->addSeconds(self::WINDOW_SECONDS));
        $failures = $fresh ? 1 : $window->failures + 1;
        $locked = $failures >= self::MAX_FAILURES ? $now->addMinutes(15) : null;
        $window->update([
            'window_started_at' => $fresh ? $now : $window->window_started_at,
            'failures' => $failures, 'locked_until' => $locked,
        ]);

        return new DomainRejection($locked !== null
            ? 'Mã không đúng. Bạn đã nhập sai '.self::MAX_FAILURES.' lần nên tạm khóa nhập mã tới '.$locked->setTimezone(Commerce::TZ)->format('H:i').'.'
            : 'Mã không đúng. Còn '.(self::MAX_FAILURES - $failures).' lần thử trước khi bị tạm khóa.', 'code');
    }
}
