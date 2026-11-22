<?php

namespace App\Actions\M18;

use App\Enums\M01\RoleCode;
use App\Enums\M08\EnrollmentValidity;
use App\Enums\M10\AttemptCloseReason;
use App\Enums\M10\AttemptStatus;
use App\Enums\M18\ExamAudience;
use App\Enums\M18\ExamStatus;
use App\Enums\M18\RegistrationStatus;
use App\Models\M04\QuizQuestion;
use App\Models\M08\Enrollment;
use App\Models\M10\AttemptAnswer;
use App\Models\M10\QuizAttempt;
use App\Models\M18\ExamConsent;
use App\Models\M18\ExamEvent;
use App\Models\M18\ExamEventCourse;
use App\Models\M18\ExamRegistration;
use App\Models\M18\ExamRegistrationLog;
use App\Models\M18\ExamTabLeave;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M10\QuizScore;
use App\Support\TestBarrier;
use App\Support\Tx;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * QT-19b (02c DK-1/2, LT-1/2): đăng ký, đồng ý công khai tên, làm bài và phép xét chốt PX (CR-111/142).
 * Lưu/nộp nhận khi t < hạn lượt (hạn lượt ≤ giờ đóng). Thứ tự khóa: users → exam_events → exam_registrations → quiz_attempts.
 */
final class ExamTaking
{
    /** b2–b3, CR-145: một bản ghi mỗi (tài khoản, kỳ); kích hoạt lại cùng bản ghi; gửi lặp không đổi gì. */
    public function register(User $user, ExamEvent $event): ExamRegistration
    {
        return Tx::run(function () use ($user, $event) {
            $user = self::userLock($user);
            $event = ExamEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $now = now()->floorMillisecond();
            if (! in_array($event->status, [ExamStatus::SapMo, ExamStatus::DangMo], true) || $now->gte($event->closes_at)) {
                throw new DomainRejection('Kỳ thi không nhận đăng ký lúc này.', 'event');
            }
            if ($event->audience === ExamAudience::Khoa && ! self::hasDesignatedEnrollment($user, $event, false)) {
                throw DomainRejection::forbidden('Kỳ này dành cho học viên các khóa chỉ định.');
            }
            $reg = ExamRegistration::query()->where('exam_event_id', $event->id)->where('user_id', $user->id)->lockForUpdate()->first();
            if ($reg === null) {
                $reg = ExamRegistration::query()->create(['exam_event_id' => $event->id, 'user_id' => $user->id, 'status' => RegistrationStatus::DaDangKy]);
                self::log($reg, 'DANG_KY', $user, $now);
            } elseif ($reg->status === RegistrationStatus::DaHuy) {
                $reg->update(['status' => RegistrationStatus::DaDangKy]);
                self::log($reg, 'KICH_HOAT_LAI', $user, $now);
            }

            return $reg;
        });
    }

    /** A2: hủy trước giờ mở (CR-76). */
    public function cancel(User $user, ExamEvent $event): ExamRegistration
    {
        return Tx::run(function () use ($user, $event) {
            $user = self::userLock($user);
            $event = ExamEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $reg = ExamRegistration::query()->where('exam_event_id', $event->id)->where('user_id', $user->id)->lockForUpdate()->first();
            $now = now()->floorMillisecond();
            if ($reg === null) {
                throw new DomainRejection('Bạn chưa đăng ký kỳ này.', 'event');
            }
            if ($now->gte($event->opens_at)) {
                throw new DomainRejection('Chỉ hủy được trước giờ mở.', 'event');
            }
            if ($reg->status === RegistrationStatus::DaDangKy) {
                $reg->update(['status' => RegistrationStatus::DaHuy]);
                self::log($reg, 'HUY', $user, $now);
            }

            return $reg;
        });
    }

    /** b3.1, CR-73: đặt/rút đồng ý công khai tên (vai Học viên), độc lập với đăng ký, trước khi công bố. */
    public function consent(User $user, ExamEvent $event, bool $consent): ExamConsent
    {
        return Tx::run(function () use ($user, $event, $consent) {
            $user = self::userLock($user);
            $event = ExamEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            return self::writeConsent($user, $event, $consent);
        });
    }

    /** LT-1 (b6–b8): một lượt mỗi (tài khoản, kỳ); thời gian = min(thời lượng, giờ đóng − lúc bắt đầu). */
    public function start(User $user, ExamEvent $event): QuizAttempt
    {
        $result = Tx::run(function () use ($user, $event) {
            $user = self::userLock($user);
            $event = ExamEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            TestBarrier::reach('exam-start.locked');
            $now = now()->floorMillisecond();
            $existing = QuizAttempt::query()->where('user_id', $user->id)->where('exam_event_id', $event->id)->lockForUpdate()->first();
            if ($existing !== null) {
                $existing = $this->settleUnderLock($event, $existing, $now);
                if ($existing->status === AttemptStatus::DangLam) {
                    return $existing;
                }

                return new DomainRejection('Bạn đã làm kỳ thi này.', 'event');
            }
            if ($event->status !== ExamStatus::DangMo || $now->lt($event->opens_at) || $now->gte($event->closes_at)) {
                return new DomainRejection('Kỳ thi chưa mở hoặc đã đóng.', 'event');
            }
            $registration = ExamRegistration::query()->where('exam_event_id', $event->id)->where('user_id', $user->id)->first();
            if ($event->requires_registration && $registration?->status !== RegistrationStatus::DaDangKy) {
                throw DomainRejection::forbidden('Kỳ này bắt buộc đăng ký trước.');
            }
            if ($event->audience === ExamAudience::Khoa && ! self::hasDesignatedEnrollment($user, $event, true)) {
                throw DomainRejection::forbidden('Cần ghi danh còn hạn của một khóa chỉ định (E33).');
            }
            // E34: vào muộn chỉ còn tới giờ đóng; hạn lượt = min(bắt đầu + thời lượng, giờ đóng).
            $deadline = $now->addMinutes($event->duration_minutes);
            $deadline = $deadline->gt($event->closes_at) ? $event->closes_at : $deadline;

            return QuizAttempt::query()->create([
                'quiz_id' => $event->quiz_id, 'exam_event_id' => $event->id,
                'exam_registration_id' => $registration?->status === RegistrationStatus::DaDangKy ? $registration->id : null,
                'user_id' => $user->id, 'attempt_no' => 1, 'started_at' => $now, 'deadline_at' => $deadline,
            ]);
        });
        if ($result instanceof DomainRejection) {
            throw $result;
        }

        return $result;
    }

    /** b10: lưu câu khi t < hạn lượt; quá hạn thì chốt theo PX và từ chối. */
    public function save(User $user, QuizAttempt $attempt, QuizQuestion $question, mixed $value): AttemptAnswer
    {
        $receivedAt = now()->floorMillisecond();
        $result = Tx::run(function () use ($user, $attempt, $question, $value, $receivedAt) {
            [$event, $attempt] = $this->ownedLock($user, $attempt);
            $attempt = $this->settleUnderLock($event, $attempt, now()->floorMillisecond());
            if ($attempt->status !== AttemptStatus::DangLam) {
                return new DomainRejection('Lượt đã chốt, câu trả lời chưa được lưu.', 'answer');
            }
            if ($question->quiz_id !== $attempt->quiz_id || $question->removed_at !== null || $question->snapshot === null) {
                throw DomainRejection::forbidden('Câu hỏi không thuộc lượt này.');
            }
            $answer = QuizScore::answer($question, $value);
            if ($answer === null && $value !== null) {
                throw new DomainRejection('Câu trả lời không hợp lệ.', 'answer');
            }

            return AttemptAnswer::query()->updateOrCreate(
                ['quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $question->id],
                ['answer' => $answer, 'received_at' => $receivedAt],
            );
        });
        if ($result instanceof DomainRejection) {
            throw $result;
        }

        return $result;
    }

    /** b11–b12: nộp khi t < hạn lượt; kèm đồng ý công khai tên chỉ khi nộp hợp lệ. Phản hồi không lộ điểm. */
    public function submit(User $user, QuizAttempt $attempt, ?bool $consent = null): QuizAttempt
    {
        $result = Tx::run(function () use ($user, $attempt, $consent) {
            [$event, $attempt] = $this->ownedLock($user, $attempt);
            $at = now()->floorMillisecond();
            $attempt = $this->settleUnderLock($event, $attempt, $at);
            if ($attempt->status !== AttemptStatus::DangLam) {
                return new DomainRejection('Lượt đã chốt trước khi nộp.', 'attempt');
            }
            $this->close($attempt, AttemptCloseReason::Nop, $at, $at);
            if ($consent !== null && $user->hasEffectiveRole(RoleCode::HocVien)) {
                self::writeConsent($user, $event, $consent);
            }

            return $attempt;
        });
        if ($result instanceof DomainRejection) {
            throw $result;
        }

        return $result;
    }

    /** b9, CR-77: ghi mọi lần rời tab; không tự hủy bài. */
    public function tabLeave(User $user, QuizAttempt $attempt, int $durationMs): void
    {
        Tx::run(function () use ($user, $attempt, $durationMs) {
            [, $attempt] = $this->ownedLock($user, $attempt);
            if ($attempt->status === AttemptStatus::DangLam) {
                ExamTabLeave::query()->create(['quiz_attempt_id' => $attempt->id, 'left_at' => now()->floorMillisecond(),
                    'duration_ms' => max(0, min($durationMs, 86_400_000))]);
            }
        });
    }

    /**
     * PX (b10.1): sự kiện hợp lệ sớm nhất thắng; trùng mốc thì thu hồi/khóa → đóng cửa sổ → hạn lượt (CR-111/142).
     * Gọi dưới khóa kỳ và lượt.
     */
    public function settleUnderLock(ExamEvent $event, QuizAttempt $attempt, CarbonImmutable $at, ?CarbonImmutable $lostAccessAt = null): QuizAttempt
    {
        if ($attempt->status !== AttemptStatus::DangLam) {
            return $attempt;
        }
        $user = User::query()->findOrFail($attempt->user_id);
        $lost = $lostAccessAt;
        if ($lost === null && (! $user->isActive() || ($event->audience === ExamAudience::Khoa && ! self::hasDesignatedEnrollment($user, $event, true, $at)))) {
            $lost = $at;
        }
        $deadline = $attempt->deadline_at;
        if ($lost !== null && $lost->lte($deadline)) {
            $this->close($attempt, AttemptCloseReason::ThuHoi, $lost, $at);
        } elseif ($at->gte($deadline)) {
            $reason = $deadline->gte($event->closes_at) ? AttemptCloseReason::DongCuaSo : AttemptCloseReason::HetGio;
            $this->close($attempt, $reason, $deadline, $at);
        }

        return $attempt;
    }

    /** QT-15/QT-01: thu hồi quyền học hoặc khóa tài khoản chốt lượt thi đang làm bị ảnh hưởng (CR-143). */
    public function onAccessLost(User $user, CarbonImmutable $at): void
    {
        $attempts = QuizAttempt::query()->where('user_id', $user->id)->whereNotNull('exam_event_id')->where('status', AttemptStatus::DangLam)->pluck('id');
        foreach ($attempts as $id) {
            Tx::run(function () use ($id, $at) {
                $eventId = QuizAttempt::query()->whereKey($id)->value('exam_event_id');
                $event = ExamEvent::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
                $attempt = QuizAttempt::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                $this->settleUnderLock($event, $attempt, $at);
            });
        }
    }

    private function close(QuizAttempt $attempt, AttemptCloseReason $reason, CarbonImmutable $eventAt, CarbonImmutable $writtenAt): void
    {
        $answers = AttemptAnswer::query()->where('quiz_attempt_id', $attempt->id)->get()->keyBy('quiz_question_id');
        $earned = BigDecimal::zero();
        $max = BigDecimal::zero();
        foreach (QuizQuestion::query()->where('quiz_id', $attempt->quiz_id)->whereNull('removed_at')->orderBy('position')->get() as $question) {
            $points = BigDecimal::of($question->points ?? '0');
            $max = $max->plus($points);
            $answer = $answers->get($question->id);
            if ($answer === null) {
                continue;
            }
            $correct = QuizScore::correct($question, $answer->answer);
            $answer->forceFill(['is_correct' => $correct, 'points_awarded' => $correct ? (string) $points : '0.00'])->save();
            if ($correct) {
                $earned = $earned->plus($points);
            }
        }
        $attempt->forceFill([
            'status' => in_array($reason, [AttemptCloseReason::HetGio, AttemptCloseReason::DongCuaSo], true) ? AttemptStatus::HetGio : AttemptStatus::DaNop,
            'close_reason' => $reason, 'closed_at' => $eventAt, 'event_at' => $eventAt, 'finalized_at' => $writtenAt,
            'score_earned' => (string) $earned, 'score_max' => (string) $max,
        ])->save();
    }

    /** @return array{0: ExamEvent, 1: QuizAttempt} */
    private function ownedLock(User $user, QuizAttempt $attempt): array
    {
        $user = self::userLock($user);
        if ($attempt->exam_event_id === null || $attempt->user_id !== $user->id) {
            throw DomainRejection::forbidden('Lượt thi không phải của bạn.');
        }
        $event = ExamEvent::query()->whereKey($attempt->exam_event_id)->lockForUpdate()->firstOrFail();
        $attempt = QuizAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

        return [$event, $attempt];
    }

    private static function writeConsent(User $user, ExamEvent $event, bool $consent): ExamConsent
    {
        if (! $user->hasEffectiveRole(RoleCode::HocVien)) {
            throw DomainRejection::forbidden('Chỉ Học viên chọn công khai tên (NH-42).');
        }
        if ($event->status === ExamStatus::DaCongBo) {
            throw new DomainRejection('Kỳ đã công bố, không đổi được lựa chọn công khai tên.', 'consent');
        }

        return ExamConsent::query()->updateOrCreate(['exam_event_id' => $event->id, 'user_id' => $user->id],
            ['consent' => $consent, 'recorded_at' => now()->floorMillisecond()]);
    }

    public static function hasDesignatedEnrollment(User $user, ExamEvent $event, bool $mustBeActive, ?CarbonImmutable $at = null): bool
    {
        $courseIds = ExamEventCourse::query()->where('exam_event_id', $event->id)->pluck('course_id');
        $enrollments = Enrollment::query()->where('user_id', $user->id)->whereIn('course_id', $courseIds)->get();

        return $mustBeActive
            ? $enrollments->contains(fn (Enrollment $e) => EnrollmentValidityChecker::for($e, $at) === EnrollmentValidity::ConHan)
            : $enrollments->isNotEmpty();
    }

    /** CR-74/112: người soạn đề và tài khoản có vai nhân sự hoặc Phụ huynh không xếp hạng (theo vai hiện tại). */
    public static function unranked(User $user, ExamEvent $event): bool
    {
        $owner = $event->quiz()->value('owner_teacher_id');

        return $user->id === $owner || $user->hasEffectiveRole(RoleCode::GiaoVien, RoleCode::GiaoVu, RoleCode::QuanTri, RoleCode::PhuHuynh);
    }

    private static function userLock(User $user): User
    {
        $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
        if (! $user->isActive()) {
            throw DomainRejection::forbidden('Tài khoản không hoạt động.');
        }

        return $user;
    }

    private static function log(ExamRegistration $reg, string $action, User $actor, CarbonImmutable $at): void
    {
        ExamRegistrationLog::query()->create(['exam_registration_id' => $reg->id, 'action' => $action, 'actor_id' => $actor->id, 'at' => $at]);
    }
}
