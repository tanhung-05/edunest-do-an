<?php

namespace App\Policies\M05;

use App\Enums\M01\RoleCode;
use App\Enums\M05\CourseVersionStatus;
use App\Models\M05\CourseVersion;
use App\Models\User;
use App\Support\M03\AuthoringScope;
use App\Support\M03\ReviewerIndependence;
use Illuminate\Auth\Access\Response;

/** 04 §4b: sửa nháp, NH-46, NH-65, NH-43, NH-44, NH-45 (công bố), NH-61. */
class CourseVersionPolicy
{
    /** Mở trình soạn (đọc): người soạn hiện tại hoặc Giáo vụ (AC-03-50: Giáo vụ xem được, không sửa). */
    public function view(User $actor, CourseVersion $version): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu) || AuthoringScope::isCurrentAuthor($actor, (int) $version->course_id);
    }

    public function edit(User $actor, CourseVersion $version): bool
    {
        return AuthoringScope::isCurrentAuthor($actor, (int) $version->course_id);
    }

    public function submit(User $actor, CourseVersion $version): bool
    {
        return AuthoringScope::isCurrentAuthor($actor, (int) $version->course_id);
    }

    public function withdraw(User $actor, CourseVersion $version): bool
    {
        return AuthoringScope::isCurrentAuthor($actor, (int) $version->course_id);
    }

    public function reopen(User $actor, CourseVersion $version): bool
    {
        return AuthoringScope::isCurrentAuthor($actor, (int) $version->course_id);
    }

    public function approve(User $actor, CourseVersion $version): Response
    {
        return $this->review($actor, $version);
    }

    public function return(User $actor, CourseVersion $version): Response
    {
        return $this->review($actor, $version);
    }

    public function publish(User $actor, CourseVersion $version): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu);
    }

    /** NH-61: xem trước bản `NHAP` (người soạn hiện tại hoặc Giáo vụ); Giáo vụ đọc bản `CHO_DUYET`. */
    public function preview(User $actor, CourseVersion $version): bool
    {
        if ($version->status !== CourseVersionStatus::Nhap) {
            return false;
        }

        return AuthoringScope::isCurrentAuthor($actor, (int) $version->course_id) || $actor->hasEffectiveRole(RoleCode::GiaoVu);
    }

    public function viewForReview(User $actor, CourseVersion $version): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu) && $version->status === CourseVersionStatus::ChoDuyet;
    }

    private function review(User $actor, CourseVersion $version): Response
    {
        if (! $actor->hasEffectiveRole(RoleCode::GiaoVu)) {
            return Response::deny('Chỉ Giáo vụ được duyệt hoặc trả lại.');
        }
        $violation = ReviewerIndependence::violation($actor, $version);

        return $violation === null ? Response::allow() : Response::deny($violation);
    }
}
