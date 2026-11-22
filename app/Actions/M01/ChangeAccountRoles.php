<?php

namespace App\Actions\M01;

use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Models\M01\Role;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\Tx;

/**
 * NH-21 — Quản trị gán/gỡ vai (QT-01 A14). Gác roles(QUAN_TRI) trước users khi đổi vai Quản trị.
 * Chặn gỡ vai Quản trị của Quản trị hoạt động cuối (CR-193). Nhật ký từ P1.
 */
final class ChangeAccountRoles
{
    public function assign(User $actor, User $target, RoleCode $role): User
    {
        return $this->run($actor, $target, $role, true);
    }

    public function remove(User $actor, User $target, RoleCode $role): User
    {
        return $this->run($actor, $target, $role, false);
    }

    private function run(User $actor, User $target, RoleCode $role, bool $assign): User
    {
        return Tx::run(function () use ($actor, $target, $role, $assign) {
            if ($role === RoleCode::QuanTri) {
                Role::lockAdminGuard();
            }
            $rows = LockedUsers::lock([$actor->id, $target->id]);
            /** @var User $me */
            $me = $rows[$actor->id];
            /** @var User $t */
            $t = $rows[$target->id];

            if (! $me->hasEffectiveRole(RoleCode::QuanTri)) {
                throw DomainRejection::forbidden('Bạn không có quyền đổi vai.');
            }
            $has = $t->hasRole($role);
            $before = $t->roles()->orderBy('code')->get()->map(fn (Role $r) => $r->code->value)->all();
            if ($assign && ! $has) {
                $t->roles()->attach(Role::idFor($role), ['assigned_by_id' => $me->id, 'assigned_at' => now()]);
            }
            if (! $assign && $has) {
                if ($role === RoleCode::QuanTri && $t->status === AccountStatus::HoatDong
                    && LockAccount::otherActiveAdmins($t->id) === 0) {
                    throw DomainRejection::forbidden('Không thể gỡ vai Quản trị hoạt động cuối.');
                }
                $t->roles()->detach(Role::idFor($role));
            }
            $t->forgetRoleCache();
            $after = $t->roles()->orderBy('code')->get()->map(fn (Role $r) => $r->code->value)->all();
            if ($before !== $after) {
                Audit::log($assign ? 'ROLE_ASSIGN' : 'ROLE_REMOVE', $me, $t, ['roles' => $before], ['roles' => $after]);
            }

            return $t;
        });
    }
}
