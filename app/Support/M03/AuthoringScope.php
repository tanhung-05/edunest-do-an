<?php

namespace App\Support\M03;

use App\Enums\M01\RoleCode;
use App\Models\M03\Course;
use App\Models\M03\CourseAuthor;
use App\Models\User;
use App\Support\DomainRejection;

/** 04 §3 — "Khóa được giao" = dòng course_authors còn hiệu lực của người đó. */
final class AuthoringScope
{
    public static function isCurrentAuthor(User $user, Course|int $course): bool
    {
        $courseId = $course instanceof Course ? $course->id : $course;

        return $user->hasEffectiveRole(RoleCode::GiaoVien)
            && CourseAuthor::query()->where('course_id', $courseId)->whereNotNull('current_key')->where('user_id', $user->id)->exists();
    }

    /** Kiểm lại dưới khóa courses (03 §14): mất quyền soạn → 403. */
    public static function ensure(User $user, Course $course): void
    {
        if (! self::isCurrentAuthor($user, $course)) {
            throw DomainRejection::forbidden('Bạn không phải người soạn hiện tại của khóa này.');
        }
    }
}
