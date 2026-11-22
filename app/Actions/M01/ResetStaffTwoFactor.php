<?php

namespace App\Actions\M01;

use App\Enums\M01\RoleCode;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\Tx;

/**
 * NH-53 — đặt lại 2FA (QT-01 A13 web: đích Giáo vụ không Quản trị; A17 lệnh máy chủ: mọi nhân sự).
 * Vô hiệu thiết lập và mã khôi phục cũ, thu hồi mọi phiên/token của đích (CR-186). Nhật ký từ P1.
 */
final class ResetStaffTwoFactor
{
    /** $actor = null nghĩa là lệnh quản trị trên máy chủ (CR-108). */
    public function handle(?User $actor, User $target): User
    {
        return Tx::run(function () use ($actor, $target) {
            $rows = LockedUsers::lock(array_values(array_filter([$actor?->id, $target->id])));
            /** @var User $t */
            $t = $rows[$target->id];

            if ($actor !== null) {
                /** @var User $me */
                $me = $rows[$actor->id];
                if (! $me->hasEffectiveRole(RoleCode::QuanTri)) {
                    throw DomainRejection::forbidden('Bạn không có quyền đặt lại 2FA.');
                }
                if (! $t->hasRole(RoleCode::GiaoVu) || $t->hasRole(RoleCode::QuanTri)) {
                    throw DomainRejection::forbidden('Chỉ đặt lại 2FA cho Giáo vụ qua giao diện.');
                }
            } elseif (! $t->hasRole(RoleCode::GiaoVu) && ! $t->hasRole(RoleCode::QuanTri)) {
                throw new DomainRejection('Tài khoản đích không phải nhân sự Giáo vụ/Quản trị.', 'email');
            }

            // AC-01-46: nhật ký đặt lại 2FA (BR-HT-54).
            Audit::log('TWO_FACTOR_RESET', $actor, $t, ['two_factor' => $t->two_factor_confirmed_at !== null ? 'DA_THIET_LAP' : 'CHUA_THIET_LAP'], ['two_factor' => 'CHUA_THIET_LAP']);
            $t->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ]);
            RevokeCredentials::apply($t);
            $t->save();

            return $t;
        });
    }
}
