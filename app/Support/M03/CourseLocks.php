<?php

namespace App\Support\M03;

use App\Models\M03\Course;
use App\Models\M05\CourseVersion;
use App\Support\DomainRejection;

/** Thứ tự khóa 05 §1: courses → (nhãn) → course_versions → questions. Gọi trong Tx::run. */
final class CourseLocks
{
    public static function course(int $courseId): Course
    {
        $course = Course::query()->whereKey($courseId)->lockForUpdate()->first();
        if ($course === null) {
            throw new DomainRejection('Không tìm thấy khóa học.', 'general', 404);
        }

        return $course;
    }

    /** Khóa courses rồi course_versions của đúng bản; bản phải thuộc khóa. */
    public static function version(int $versionId): CourseVersion
    {
        $courseId = CourseVersion::query()->whereKey($versionId)->value('course_id');
        if (! is_numeric($courseId)) {
            throw new DomainRejection('Không tìm thấy phiên bản.', 'general', 404);
        }
        $course = self::course((int) $courseId);
        $version = CourseVersion::query()->whereKey($versionId)->lockForUpdate()->firstOrFail();
        $version->setRelation('course', $course);

        return $version;
    }
}
