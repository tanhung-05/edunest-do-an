<?php

namespace App\Actions\M14;

use App\Enums\M08\EnrollmentValidity;
use App\Enums\M14\RatingStatus;
use App\Models\M03\Course;
use App\Models\M08\Enrollment;
use App\Models\M09\LessonProgress;
use App\Models\M14\CourseRating;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\AuthoringScope;
use App\Support\M07\OrderLocks;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M09\LearningAccess;
use App\Support\Tx;

/**
 * QT-16 (DG-1..DG-5): viết/sửa, trả lời, ẩn, hiện lại. Khóa `users` → `courses` → `enrollments` → `course_ratings`.
 * Điểm trung bình và số đánh giá chỉ tính dòng `HIEN`, cập nhật cùng giao dịch.
 */
final class CourseRatings
{
    public const MIN_PROGRESS_PERCENT = 30;

    public function submit(User $actor, Course $course, int $stars, ?string $comment): CourseRating
    {
        if ($stars < 1 || $stars > 5) {
            throw new DomainRejection('Chọn từ 1 đến 5 sao.', 'stars');
        }
        $comment = $comment === null ? null : trim($comment);
        if ($comment !== null && mb_strlen($comment) > 2000) {
            throw new DomainRejection('Nhận xét tối đa 2.000 ký tự.', 'comment');
        }

        return Tx::run(function () use ($actor, $course, $stars, $comment) {
            $student = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            OrderLocks::student($student);
            $course = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $enrollment = Enrollment::query()->where('user_id', $student->id)->where('course_id', $course->id)->lockForUpdate()->first();
            if ($enrollment === null) {
                throw DomainRejection::forbidden('Bạn chưa ghi danh khóa này.');
            }
            $validity = EnrollmentValidityChecker::for($enrollment);
            if ($validity !== EnrollmentValidity::ConHan) {
                throw new DomainRejection($validity === EnrollmentValidity::DaThuHoi
                    ? 'Quyền học đã bị thu hồi nên không đánh giá được.' : 'Quyền học đã hết hạn nên không đánh giá được.', 'stars');
            }
            [$done, $total] = self::progress($enrollment);
            // CR-39: so ngưỡng bằng giá trị chưa làm tròn: done/total ≥ 30% ⇔ 100·done ≥ 30·total.
            if ($total === 0 || $done * 100 < self::MIN_PROGRESS_PERCENT * $total) {
                throw new DomainRejection('Cần học tối thiểu 30% bài bắt buộc để đánh giá (hiện '.$done.'/'.$total.' bài).', 'stars');
            }
            $rating = CourseRating::query()->where('user_id', $student->id)->where('course_id', $course->id)->lockForUpdate()->first();
            if ($rating !== null && $rating->status === RatingStatus::DaAn) {
                throw new DomainRejection('Đánh giá của bạn đang bị ẩn ('.$rating->hidden_reason.'). Chỉ sửa được sau khi giáo vụ hiện lại.', 'stars');
            }
            if ($rating === null) {
                $rating = CourseRating::query()->create([
                    'user_id' => $student->id, 'course_id' => $course->id, 'enrollment_id' => $enrollment->id,
                    'stars' => $stars, 'comment' => $comment === '' ? null : $comment, 'status' => RatingStatus::Hien,
                ]);
            } else {
                $rating->update(['stars' => $stars, 'comment' => $comment === '' ? null : $comment]);
            }
            self::recalculate($course);

            return $rating;
        });
    }

    public function reply(User $actor, CourseRating $rating, string $reply): CourseRating
    {
        $reply = trim($reply);
        if ($reply === '' || mb_strlen($reply) > 2000) {
            throw new DomainRejection('Câu trả lời từ 1 đến 2.000 ký tự.', 'reply');
        }

        return Tx::run(function () use ($actor, $rating, $reply) {
            $teacher = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $course = Course::query()->whereKey($rating->course_id)->lockForUpdate()->firstOrFail();
            AuthoringScope::ensure($teacher, $course);
            $rating = CourseRating::query()->whereKey($rating->id)->lockForUpdate()->firstOrFail();
            $rating->update(['reply' => $reply, 'replied_by_id' => $teacher->id, 'replied_at' => now()]);

            return $rating;
        });
    }

    public function hide(User $actor, CourseRating $rating, string $reason): CourseRating
    {
        return $this->moderate($actor, $rating, $reason, RatingStatus::DaAn);
    }

    public function unhide(User $actor, CourseRating $rating, string $reason): CourseRating
    {
        return $this->moderate($actor, $rating, $reason, RatingStatus::Hien);
    }

    private function moderate(User $actor, CourseRating $rating, string $reason, RatingStatus $to): CourseRating
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainRejection('Nhập lý do từ 1 đến 500 ký tự.', 'reason');
        }

        return Tx::run(function () use ($actor, $rating, $reason, $to) {
            $locked = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            OrderLocks::academic($locked);
            $course = Course::query()->whereKey($rating->course_id)->lockForUpdate()->firstOrFail();
            $rating = CourseRating::query()->whereKey($rating->id)->lockForUpdate()->firstOrFail();
            if ($rating->status === $to) {
                throw new DomainRejection($to === RatingStatus::DaAn ? 'Đánh giá đã bị ẩn.' : 'Đánh giá đang hiển thị.');
            }
            $rating->update($to === RatingStatus::DaAn
                ? ['status' => $to, 'hidden_reason' => $reason, 'hidden_at' => now(), 'hidden_by_id' => $actor->id]
                : ['status' => $to, 'hidden_reason' => null, 'hidden_at' => null, 'hidden_by_id' => null]);
            self::recalculate($course);
            Audit::log($to === RatingStatus::DaAn ? 'RATING_HIDE' : 'RATING_UNHIDE', $actor, $rating,
                ['status' => $to === RatingStatus::DaAn ? 'HIEN' : 'DA_AN'], ['status' => $to->value], $reason);

            return $rating;
        });
    }

    /** @return array{0: int, 1: int} số bài bắt buộc đã xong và tổng */
    public static function progress(Enrollment $enrollment): array
    {
        $required = LearningAccess::lessons($enrollment)->where('is_required', true);
        $done = LessonProgress::query()->where('enrollment_id', $enrollment->id)
            ->whereIn('lesson_id', $required->modelKeys())->whereNotNull('completed_at')->count();

        return [$done, $required->count()];
    }

    /** A8: không có đánh giá hiển thị → NULL ("Chưa có đánh giá"). Gọi dưới khóa `courses`. */
    public static function recalculate(Course $course): void
    {
        $visible = CourseRating::query()->where('course_id', $course->id)->where('status', RatingStatus::Hien);
        $count = (clone $visible)->count();
        $sum = (int) (clone $visible)->sum('stars');
        $course->forceFill([
            'rating_count' => $count,
            'rating_avg' => $count === 0 ? null : number_format(intdiv($sum * 100 + intdiv($count, 2), $count) / 100, 2, '.', ''),
        ])->save();
    }
}
