<?php

namespace App\Actions\M01;

use App\Actions\M10\FinalizeAttempt;
use App\Actions\M18\ExamTaking;
use App\Actions\M20\PracticeSessions;
use App\Actions\M22\GameSessions;
use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Events\M01\AccountLocked;
use App\Models\M01\Role;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\TestBarrier;
use App\Support\Tx;

/**
 * TK-3 HOAT_DONG → BI_KHOA. Khóa: roles(QUAN_TRI) → users (theo id) [→ enrollments → quiz_attempts từ P4].
 */
final class LockAccount
{
    public function handle(User $actor, User $target, ?string $reason = null): User
    {
        $result = Tx::run(function () use ($actor, $target, $reason) {
            Role::lockAdminGuard();
            $rows = LockedUsers::lock([$actor->id, $target->id]);
            /** @var User $me */
            $me = $rows[$actor->id];
            /** @var User $t */
            $t = $rows[$target->id];
            TestBarrier::reach('lock-account.locked');

            if ($me->id === $t->id) {
                throw DomainRejection::forbidden('Không thể tự khóa tài khoản của mình.');
            }
            if (! $t->isActive()) {
                return ['user' => $t, 'changed' => false]; // gọi lặp: đã khóa, không ghi
            }
            if ($t->hasRole(RoleCode::QuanTri) && self::otherActiveAdmins($t->id) === 0) {
                throw DomainRejection::forbidden('Không thể khóa Quản trị hoạt động cuối cùng.');
            }
            if (! $me->hasEffectiveRole(RoleCode::QuanTri)) {
                throw DomainRejection::forbidden('Bạn không còn quyền Quản trị.');
            }

            Audit::log('ACCOUNT_LOCK', $me, $t, ['status' => AccountStatus::HoatDong->value], ['status' => AccountStatus::BiKhoa->value], $reason);
            $t->status = AccountStatus::BiKhoa;
            $t->locked_at = now();
            $t->locked_reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;
            RevokeCredentials::apply($t);
            $t->save();
            app(FinalizeAttempt::class)->forAccountLock($t);
            app(ExamTaking::class)->onAccessLost($t, now()->floorMillisecond());
            // X5 / CR-155: phiên luyện tập và game đang làm chốt BO_DO.
            app(PracticeSessions::class)->closeForUser($t, now()->floorMillisecond());
            app(GameSessions::class)->closeForUser($t, now()->floorMillisecond());

            return ['user' => $t, 'changed' => true];
        });

        if ($result['changed']) {
            AccountLocked::dispatch($target->id, $actor->id);
        }

        return $result['user'];
    }

    /** Đếm Quản trị HOAT_DONG khác đích, đọc dưới gác (dữ liệu hiện hành). */
    public static function otherActiveAdmins(int $exceptUserId): int
    {
        return User::query()
            ->where('status', AccountStatus::HoatDong->value)
            ->where('id', '!=', $exceptUserId)
            ->whereHas('roles', fn ($q) => $q->where('code', RoleCode::QuanTri->value))
            ->count();
    }
}
