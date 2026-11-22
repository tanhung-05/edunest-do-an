<?php

namespace App\Actions\M22;

use App\Enums\M01\RoleCode;
use App\Enums\M08\EnrollmentValidity;
use App\Enums\M22\ReviewGrade;
use App\Models\M08\Enrollment;
use App\Models\M22\Flashcard;
use App\Models\M22\FlashcardReview;
use App\Models\M22\FlashcardReviewLog;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * QT-24 bước 5–6: tự chấm thẻ theo SM-2 rút gọn (M22, CR-90). Khóa (học viên, khóa) rồi khóa thẻ, cùng thứ tự với
 * sửa mặt sau (A1) nên lượt chấm mở trước lần sửa bị từ chối (CR-161).
 */
final class FlashcardReviews
{
    public const START_EASE = 2.5;

    public const MIN_EASE = 1.3;

    /** Ghi danh của học viên ở khóa; `$requireActive` thì phải còn hạn (BR-XP-64). Gọi trong transaction. */
    public static function activeEnrollment(User $student, int $courseId, CarbonImmutable $at, bool $lock = false): Enrollment
    {
        $query = Enrollment::query()->where('user_id', $student->id)->where('course_id', $courseId);
        $enrollment = ($lock ? $query->lockForUpdate() : $query)->first();
        if ($enrollment === null) {
            throw DomainRejection::forbidden('Bạn chưa ghi danh khóa của bộ thẻ này.');
        }
        $validity = EnrollmentValidityChecker::for($enrollment, $at);
        if ($validity === EnrollmentValidity::DaThuHoi) {
            throw DomainRejection::forbidden('Quyền học của khóa đã bị thu hồi.');
        }
        if ($validity !== EnrollmentValidity::ConHan) {
            throw new DomainRejection('Ghi danh đã hết hạn, không ôn thẻ hoặc chơi game được.');
        }

        return $enrollment;
    }

    /**
     * Tính lượt ôn kế tiếp. Lần đầu (reps = 0): 1/1/3/4 ngày. Lần sau: Quên 1; Khó × 1,2; Nhớ × hệ số; Dễ × hệ số × 1,3.
     * Hệ số: Quên −0,2; Khó −0,15; Nhớ giữ; Dễ +0,15; sàn 1,3. Dùng hệ số trước cập nhật.
     *
     * @return array{ease: float, interval: float, days: int}
     */
    public static function schedule(ReviewGrade $grade, float $ease, ?float $interval, int $reps): array
    {
        $first = $reps === 0 || $interval === null;
        $next = match ($grade) {
            ReviewGrade::Quen => 1.0,
            ReviewGrade::Kho => $first ? 1.0 : $interval * 1.2,
            ReviewGrade::Nho => $first ? 3.0 : $interval * $ease,
            ReviewGrade::De => $first ? 4.0 : $interval * $ease * 1.3,
        };
        $newEase = max(self::MIN_EASE, round($ease + match ($grade) {
            ReviewGrade::Quen => -0.2,
            ReviewGrade::Kho => -0.15,
            ReviewGrade::Nho => 0.0,
            ReviewGrade::De => 0.15,
        }, 2));
        $next = round($next, 3);

        return ['ease' => $newEase, 'interval' => $next, 'days' => (int) ceil($next - 1e-9)];
    }

    public function grade(User $user, Flashcard $card, ReviewGrade $grade, int $backVersion, string $requestId, ?CarbonImmutable $at = null): FlashcardReview
    {
        $at ??= now()->floorMillisecond();

        return Tx::run(function () use ($user, $card, $grade, $backVersion, $requestId, $at) {
            $student = User::query()->whereKey($user->id)->first();
            if ($student === null || ! $student->isActive() || ! $student->hasEffectiveRole(RoleCode::HocVien)) {
                throw DomainRejection::forbidden('Chỉ học viên ôn thẻ được.');
            }
            $courseId = (int) DB::table('flashcard_sets')->where('id', $card->flashcard_set_id)->value('course_id');
            self::activeEnrollment($student, $courseId, $at, true);
            $card = Flashcard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            $existing = FlashcardReviewLog::query()->where('request_id', $requestId)->first();
            if ($existing !== null) {
                return FlashcardReview::query()->whereKey($existing->flashcard_review_id)->firstOrFail();
            }
            if ($card->archived_at !== null || DB::table('flashcard_sets')->where('id', $card->flashcard_set_id)->whereNotNull('archived_at')->exists()) {
                throw new DomainRejection('Thẻ không còn dùng.');
            }
            if ($backVersion !== $card->back_version) {
                throw new DomainRejection('Mặt sau của thẻ vừa được sửa. Mở lại thẻ để ôn với nội dung mới.');
            }
            DB::table('flashcard_reviews')->insertOrIgnore(['flashcard_id' => $card->id, 'user_id' => $student->id, 'course_id' => $courseId,
                'ease' => self::START_EASE, 'reps' => 0, 'back_version' => $card->back_version, 'created_at' => now(), 'updated_at' => now()]);
            $review = FlashcardReview::query()->where('flashcard_id', $card->id)->where('user_id', $student->id)->lockForUpdate()->firstOrFail();
            if ($review->back_version !== $card->back_version) {
                $review->forceFill(['ease' => self::START_EASE, 'interval_days' => null, 'reps' => 0, 'due_on' => null, 'back_version' => $card->back_version]);
            }
            $ease = (float) $review->ease;
            $interval = $review->interval_days === null ? null : (float) $review->interval_days;
            $next = self::schedule($grade, $ease, $interval, $review->reps);
            $due = $at->setTimezone('Asia/Ho_Chi_Minh')->startOfDay()->addDays($next['days'])->toDateString();
            $review->forceFill(['ease' => $next['ease'], 'interval_days' => $next['interval'], 'reps' => $review->reps + 1, 'due_on' => $due, 'last_reviewed_at' => $at])->save();
            FlashcardReviewLog::query()->create(['flashcard_review_id' => $review->id, 'request_id' => $requestId, 'grade' => $grade,
                'ease_before' => $ease, 'ease_after' => $next['ease'], 'interval_before' => $interval, 'interval_after' => $next['interval'],
                'due_on' => $due, 'reviewed_at' => $at]);

            return $review;
        });
    }
}
