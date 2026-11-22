<?php

namespace App\Policies\M01;

use App\Enums\M01\RoleCode;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** 04 §4a — NH-20, NH-21, NH-22, NH-53, NH-87. Điều kiện trạng thái kiểm lại trong Action dưới khóa. */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasEffectiveRole(RoleCode::QuanTri);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasEffectiveRole(RoleCode::QuanTri);
    }

    /** NH-22: Giáo vụ cấp Giáo viên; Quản trị cấp Giáo vụ và vai khác. */
    public function create(User $actor, RoleCode $role): bool
    {
        return $role === RoleCode::GiaoVien
            ? $actor->hasEffectiveRole(RoleCode::GiaoVu, RoleCode::QuanTri)
            : $actor->hasEffectiveRole(RoleCode::QuanTri);
    }

    /** NH-20: không tự khóa (A16). Quản trị hoạt động cuối kiểm dưới gác trong LockAccount. */
    public function lock(User $actor, User $target): Response
    {
        if (! $actor->hasEffectiveRole(RoleCode::QuanTri)) {
            return Response::deny('Bạn không có quyền khóa tài khoản.');
        }

        return $actor->id === $target->id
            ? Response::deny('Không thể tự khóa tài khoản của mình.')
            : Response::allow();
    }

    public function unlock(User $actor, User $target): bool
    {
        return $actor->hasEffectiveRole(RoleCode::QuanTri);
    }

    /** NH-21: chỉ Quản trị gán/gỡ vai (Giáo vụ chỉ gán Giáo viên khi cấp tài khoản, qua create). */
    public function assignRole(User $actor, User $target, RoleCode $role): bool
    {
        return $actor->hasEffectiveRole(RoleCode::QuanTri);
    }

    public function removeRole(User $actor, User $target, RoleCode $role): bool
    {
        return $actor->hasEffectiveRole(RoleCode::QuanTri);
    }

    /** NH-53 web: đích có Giáo vụ và không có Quản trị (kể cả vai Quản trị chưa hiệu lực). */
    public function resetTwoFactor(User $actor, User $target): Response
    {
        if (! $actor->hasEffectiveRole(RoleCode::QuanTri)) {
            return Response::deny('Bạn không có quyền đặt lại 2FA.');
        }

        return $target->hasRole(RoleCode::GiaoVu) && ! $target->hasRole(RoleCode::QuanTri)
            ? Response::allow()
            : Response::deny('Chỉ đặt lại 2FA cho Giáo vụ qua giao diện. Quản trị dùng lệnh trên máy chủ.');
    }

    /** NH-87 (CR-191): Giáo vụ đọc danh sách Giáo viên. */
    public function viewTeachers(User $actor): bool
    {
        return $actor->hasEffectiveRole(RoleCode::GiaoVu);
    }
}
