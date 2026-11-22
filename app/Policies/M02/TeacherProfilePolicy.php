<?php

namespace App\Policies\M02;

use App\Enums\M01\RoleCode;
use App\Models\M02\TeacherProfile;
use App\Models\User;

/** 04 §4a NH-54 (CR-08): Giáo vụ lập hồ sơ; Giáo viên chỉ sửa hồ sơ hiện có của mình; Quản trị chỉ xem. */
class TeacherProfilePolicy
{
    public function create(User $actor): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu);
    }

    public function update(User $actor, TeacherProfile $profile): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVien) && $profile->user_id === $actor->id;
    }
}
