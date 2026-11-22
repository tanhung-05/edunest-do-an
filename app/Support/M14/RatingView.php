<?php

namespace App\Support\M14;

use App\Actions\M14\CourseRatings;
use App\Enums\M08\EnrollmentValidity;
use App\Enums\M14\RatingStatus;
use App\Models\M03\Course;
use App\Models\M08\Enrollment;
use App\Models\M14\CourseRating;
use App\Models\User;
use App\Support\M08\EnrollmentValidityChecker;

/**
 * QT-16 b3, b5, b7, A8, E47, CR-135: người khác chỉ thấy đánh giá đang hiển thị; câu trả lời chỉ công khai khi đánh giá
 * cha đang hiển thị; người viết thấy đánh giá bị ẩn của mình kèm lý do.
 */
final class RatingView
{
    /** @return array<string, mixed> */
    public static function forCourse(Course $course, ?User $viewer, ?Enrollment $enrollment): array
    {
        $visible = CourseRating::query()->where('course_id', $course->id)->where('status', RatingStatus::Hien);
        $distribution = [];
        foreach ((clone $visible)->selectRaw('stars, count(*) as n')->groupBy('stars')->pluck('n', 'stars') as $stars => $n) {
            $distribution[(int) $stars] = (int) $n;
        }
        $own = $viewer === null ? null : CourseRating::query()->where('course_id', $course->id)->where('user_id', $viewer->id)->first();
        $canRate = false;
        $progress = null;
        if ($enrollment !== null && $viewer !== null && $enrollment->user_id === $viewer->id) {
            [$done, $total] = CourseRatings::progress($enrollment);
            $progress = ['done' => $done, 'total' => $total];
            $canRate = EnrollmentValidityChecker::for($enrollment) === EnrollmentValidity::ConHan
                && $total > 0 && $done * 100 >= CourseRatings::MIN_PROGRESS_PERCENT * $total
                && ($own === null || $own->status === RatingStatus::Hien);
        }

        return [
            'avg' => $course->rating_avg,
            'count' => $course->rating_count,
            'distribution' => array_map(fn (int $s) => $distribution[$s] ?? 0, [5, 4, 3, 2, 1]),
            'items' => (clone $visible)->with('user:id,name')->latest('id')->limit(20)->get()->map(fn (CourseRating $r) => self::row($r))->values(),
            'own' => $own === null ? null : [...self::row($own), 'hidden' => $own->status === RatingStatus::DaAn,
                'hidden_reason' => $own->status === RatingStatus::DaAn ? $own->hidden_reason : null],
            'can_rate' => $canRate,
            'progress' => $progress,
            'store_url' => $enrollment !== null ? route('student.ratings.store', $course) : null,
        ];
    }

    /** @return array<string, mixed> */
    private static function row(CourseRating $r): array
    {
        $public = $r->status === RatingStatus::Hien;

        return [
            'id' => $r->id, 'student' => $r->user->name ?? '', 'stars' => $r->stars, 'comment' => $r->comment,
            'reply' => $public ? $r->reply : null, 'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
