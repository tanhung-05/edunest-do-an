<?php

namespace App\Policies\M03;

use App\Enums\M01\RoleCode;
use App\Models\M03\Course;
use App\Models\User;
use App\Support\M03\AuthoringScope;

/** 04 §4b: NH-47, NH-63, NH-62, NH-09, NH-45 (bán) và tạo bản nháp (09 §2.C). */
class CoursePolicy
{
    /** Tạo khóa và giao soạn (NH-47): chỉ Giáo vụ. */
    public function create(User $actor): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu);
    }

    public function viewAny(User $actor): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu);
    }

    /** Xem thông tin khóa ở vùng nhân sự: Giáo vụ, hoặc người soạn hiện tại. */
    public function view(User $actor, Course $course): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu) || AuthoringScope::isCurrentAuthor($actor, $course);
    }

    public function reassignAuthor(User $actor, Course $course): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu);
    }

    public function update(User $actor, Course $course): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu);
    }

    public function attachTags(User $actor, Course $course): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu);
    }

    public function updatePricing(User $actor, Course $course): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu);
    }

    /** Ảnh bìa: Giáo vụ hoặc người soạn hiện tại (AC-03-72). */
    public function updateCover(User $actor, Course $course): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu) || AuthoringScope::isCurrentAuthor($actor, $course);
    }

    public function startSelling(User $actor, Course $course): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu);
    }

    public function stopSelling(User $actor, Course $course): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu);
    }

    public function resumeSelling(User $actor, Course $course): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu);
    }

    public function createDraftVersion(User $actor, Course $course): bool
    {
        return AuthoringScope::isCurrentAuthor($actor, $course);
    }

    /** Ngân hàng câu hỏi của khóa (09 §2.D): người soạn hiện tại. */
    public function manageQuestionBanks(User $actor, Course $course): bool
    {
        return AuthoringScope::isCurrentAuthor($actor, $course);
    }
}
