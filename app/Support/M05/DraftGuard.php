<?php

namespace App\Support\M05;

use App\Enums\M05\CourseVersionStatus;
use App\Models\M05\CourseVersion;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M03\AuthoringScope;
use App\Support\M03\CourseLocks;
use Illuminate\Support\Facades\DB;

/**
 * Điểm vào chung của EditDraftContent (03 §3): khóa courses → course_versions, đọc lại người soạn hiện tại
 * và trạng thái `NHAP`, ghi người sửa (L13, insert-ignore). Gọi trong Tx::run.
 */
final class DraftGuard
{
    public static function open(User $actor, int $versionId): CourseVersion
    {
        $version = CourseLocks::version($versionId);
        AuthoringScope::ensure($actor, $version->course);
        self::ensureDraft($version);
        DB::table('course_version_editors')->insertOrIgnore([
            'course_version_id' => $version->id,
            'user_id' => $actor->id,
            'first_edited_at' => now(),
        ]);

        return $version;
    }

    public static function ensureDraft(CourseVersion $version): void
    {
        $message = match ($version->status) {
            CourseVersionStatus::Nhap => null,
            CourseVersionStatus::ChoDuyet => 'Bản đang chờ duyệt: hãy rút về nháp để sửa.',
            CourseVersionStatus::TraLai => 'Bản bị trả lại: hãy bấm "Bắt đầu sửa" trước.',
            CourseVersionStatus::DaDuyet => 'Bản đã duyệt không sửa được.',
            default => 'Bản đã công bố không sửa được: hãy tạo bản nháp mới.',
        };
        if ($message !== null) {
            throw new DomainRejection($message, 'general');
        }
    }
}
