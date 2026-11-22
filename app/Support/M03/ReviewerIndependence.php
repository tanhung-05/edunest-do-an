<?php

namespace App\Support\M03;

use App\Models\M03\CourseAuthor;
use App\Models\M05\CourseVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * E6 (CR-106, BR-ND-06): người duyệt/trả lại chưa từng được giao soạn khóa (kể cả đã giao lại)
 * và chưa từng sửa bản đang xét.
 */
final class ReviewerIndependence
{
    public const DA_TUNG_GIAO = 'Bạn từng được giao soạn khóa này nên không được duyệt hoặc trả lại.';

    public const DA_SUA = 'Bạn đã sửa bản đang xét nên không được duyệt hoặc trả lại.';

    /** Trả lý do vi phạm, hoặc null khi độc lập. Lý do "đã sửa" được ưu tiên báo (AC-05-38). */
    public static function violation(User $user, CourseVersion $version): ?string
    {
        if (DB::table('course_version_editors')->where('course_version_id', $version->id)->where('user_id', $user->id)->exists()) {
            return self::DA_SUA;
        }
        if (CourseAuthor::query()->where('course_id', $version->course_id)->where('user_id', $user->id)->exists()) {
            return self::DA_TUNG_GIAO;
        }

        return null;
    }

    public static function check(User $user, CourseVersion $version): bool
    {
        return self::violation($user, $version) === null;
    }
}
