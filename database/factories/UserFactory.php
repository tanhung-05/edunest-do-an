<?php

namespace Database\Factories;

use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Models\M01\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * State theo 07 §3: HOAT_DONG/BI_KHOA, email đã/chưa xác minh, mật khẩu tạm, 2FA đã/chưa xác nhận, vai.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public const PASSWORD = 'password';

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => Str::lower(Str::random(10)).'@edunest.test',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(self::PASSWORD),
            'remember_token' => Str::random(10),
            'status' => AccountStatus::HoatDong,
            'auth_generation' => 0,
            'must_change_password' => false,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['status' => AccountStatus::BiKhoa, 'locked_at' => now()]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn () => ['must_change_password' => true]);
    }

    /** 2FA đã xác nhận với bí mật TOTP thật (sinh mã bằng Google2FA trong test). */
    public function withTwoFactor(): static
    {
        return $this->state(function () {
            $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();

            return [
                'two_factor_secret' => encrypt($secret),
                'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1', 'recovery-code-2'])),
                'two_factor_confirmed_at' => now(),
            ];
        });
    }

    public function role(RoleCode ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles) {
            foreach ($roles as $role) {
                $user->roles()->syncWithoutDetaching([Role::idFor($role) => ['assigned_at' => now()]]);
            }
            $user->forgetRoleCache();
        });
    }

    public function student(): static
    {
        return $this->role(RoleCode::HocVien);
    }

    public function teacher(): static
    {
        return $this->role(RoleCode::GiaoVien);
    }

    /** Giáo vụ đã có 2FA (vai hiệu lực). */
    public function academic(): static
    {
        return $this->withTwoFactor()->role(RoleCode::GiaoVu);
    }

    /** Quản trị đã có 2FA (vai hiệu lực). */
    public function admin(): static
    {
        return $this->withTwoFactor()->role(RoleCode::QuanTri);
    }
}
