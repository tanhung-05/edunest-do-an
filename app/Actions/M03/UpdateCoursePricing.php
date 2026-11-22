<?php

namespace App\Actions\M03;

use App\Models\M03\Course;
use App\Models\User;
use App\Support\Audit;
use App\Support\M03\CourseLocks;
use App\Support\Tx;

/** NH-09 (CR-13, BR-TT-79): đổi giá; kiểm bộ giá kết quả dưới khóa courses, ghi nhật ký đổi giá. */
final class UpdateCoursePricing
{
    public function handle(User $actor, Course $course, int $price, ?int $original, ?int $extension): Course
    {
        return Tx::run(function () use ($actor, $course, $price, $original, $extension) {
            $course = CourseLocks::course($course->id);
            CoursePricing::validate($price, $original, $course->access_months, $extension);
            $old = ['price' => $course->price, 'original_price' => $course->original_price, 'extension_price' => $course->extension_price];
            $new = ['price' => $price, 'original_price' => $original, 'extension_price' => $extension];
            if ($old === $new) {
                return $course;
            }
            $course->forceFill($new)->save();
            Audit::log('COURSE_PRICE', $actor, $course, $old, $new);

            return $course;
        });
    }
}
