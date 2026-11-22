<?php

namespace App\Support\M03;

use App\Models\M08\Enrollment;

/** CR-21: khóa đã có ghi danh thì chỉ sửa giá. */
final class CourseEnrollments
{
    public static function exists(int $courseId): bool
    {
        return Enrollment::query()->where('course_id', $courseId)->exists();
    }
}
