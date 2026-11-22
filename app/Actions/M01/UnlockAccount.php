<?php

namespace App\Actions\M01;

use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Models\M01\Role;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\Tx;

/** TK-4 BI_KHOA → HOAT_DONG. Khóa: roles(QUAN_TRI) → users. Phiên cũ vẫn vô hiệu (thế hệ đã tăng ở TK-3). */
final class UnlockAccount
{
    public function handle(User $actor, User $target): User
    {
        return Tx::run(function () use ($actor, $target) {
            Role::lockAdminGuard();
            $rows = LockedUsers::lock([$actor->id, $target->id]);
            /** @var User $me */
            $me = $rows[$actor->id];
            /** @var User $t */
            $t = $rows[$target->id];

            if (! $me->hasEffectiveRole(RoleCode::QuanTri)) {
                throw DomainRejection::forbidden('Bạn không còn quyền Quản trị.');
            }
            if ($t->isActive()) {
                return $t;
            }
            Audit::log('ACCOUNT_UNLOCK', $actor, $t, ['status' => AccountStatus::BiKhoa->value], ['status' => AccountStatus::HoatDong->value]);
            $t->status = AccountStatus::HoatDong;
            $t->locked_at = null;
            $t->locked_reason = null;
            $t->save();

            return $t;
        });
    }
}
