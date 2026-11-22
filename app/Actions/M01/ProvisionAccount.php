<?php

namespace App\Actions\M01;

use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Models\M01\Role;
use App\Models\M02\TeacherProfile;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\Tx;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

/**
 * TK-2 — cấp tài khoản (QT-01 A9/A10, QT-02 b4). Một giao dịch: tài khoản + vai + hồ sơ (luồng Giáo vụ).
 * Email coi là đã xác minh, buộc đổi mật khẩu lần đầu. Gác roles(QUAN_TRI) khi tạo Quản trị.
 */
final class ProvisionAccount
{
    /**
     * @param  array{bio?: ?string, achievements?: ?string, photo_path?: ?string}|null  $profile
     */
    public function handle(User $actor, string $name, string $email, string $password, RoleCode $role, ?array $profile = null): User
    {
        $email = User::normalizeEmail($email);

        try {
            return Tx::run(function () use ($actor, $name, $email, $password, $role, $profile) {
                if ($role === RoleCode::QuanTri) {
                    Role::lockAdminGuard();
                }
                /** @var User $me */
                $me = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $allowed = $role === RoleCode::GiaoVien
                    ? $me->hasEffectiveRole(RoleCode::GiaoVu, RoleCode::QuanTri)
                    : $me->hasEffectiveRole(RoleCode::QuanTri);
                if (! $allowed) {
                    throw DomainRejection::forbidden('Bạn không có quyền cấp tài khoản vai này.');
                }
                if (User::query()->where('email', $email)->exists()) {
                    throw new DomainRejection('Email này đã có tài khoản.', 'email');
                }

                $user = new User(['name' => trim($name), 'email' => $email, 'password' => Hash::make($password)]);
                $user->email_verified_at = now();
                $user->must_change_password = true;
                $user->status = AccountStatus::HoatDong;
                $user->auth_generation = 0;
                $user->created_by_id = $me->id;
                $user->save();
                $user->roles()->attach(Role::idFor($role), ['assigned_by_id' => $me->id, 'assigned_at' => now()]);

                if ($profile !== null && $role === RoleCode::GiaoVien && $me->hasEffectiveRole(RoleCode::GiaoVu)) {
                    TeacherProfile::query()->create([
                        'user_id' => $user->id,
                        'bio' => $profile['bio'] ?? null,
                        'achievements' => $profile['achievements'] ?? null,
                        'photo_path' => $profile['photo_path'] ?? null,
                        'created_by_id' => $me->id,
                    ]);
                }
                // AC-01-24: nhật ký tạo tài khoản (BR-HT-27).
                Audit::log('ACCOUNT_CREATE', $me, $user, null, ['status' => AccountStatus::HoatDong->value, 'roles' => [$role->value]]);

                return $user;
            });
        } catch (QueryException $e) {
            if (Tx::code($e) === 1062) {
                throw new DomainRejection('Email này đã có tài khoản.', 'email');
            }
            throw $e;
        }
    }
}
