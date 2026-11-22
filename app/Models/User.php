<?php

namespace App\Models;

use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Models\M01\Role;
use App\Models\M01\UserDevice;
use App\Models\M02\TeacherProfile;
use App\Models\M03\CourseAuthor;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * L01 users. Không xóa tài khoản (CR-12); không passkey (01 Q-01).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property AccountStatus $status
 * @property CarbonImmutable|null $locked_at
 * @property string|null $locked_reason
 * @property int $auth_generation
 * @property bool $must_change_password
 * @property int|null $created_by_id
 * @property string|null $avatar_path
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property list<string>|null $notification_mutes
 * @property string|null $nickname
 * @property bool $show_name_on_leaderboard
 */
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /** Chỉ các trường do Action ghi; không mass-assign trạng thái từ yêu cầu. */
    protected $fillable = ['name', 'email', 'password'];

    /** @var list<RoleCode>|null */
    private ?array $roleCodesCache = null;

    private ?string $deviceRememberToken = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'status' => AccountStatus::class,
            'locked_at' => 'datetime',
            'auth_generation' => 'integer',
            'must_change_password' => 'boolean',
            'notification_mutes' => 'array',
            'show_name_on_leaderboard' => 'boolean',
        ];
    }

    /** BR-TB-18: token ghi nhớ của thiết bị hiện tại (không lưu vào `users.remember_token`). */
    public function useDeviceRememberToken(string $token): void
    {
        $this->deviceRememberToken = $token;
    }

    public function getRememberToken()
    {
        return $this->deviceRememberToken ?? parent::getRememberToken();
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot(['assigned_by_id', 'assigned_at'])->withTimestamps();
    }

    /** @return HasMany<CourseAuthor, $this> */
    public function courseAuthorships(): HasMany
    {
        return $this->hasMany(CourseAuthor::class);
    }

    /** @return HasOne<TeacherProfile, $this> */
    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    /** @return HasMany<UserDevice, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /** Email luôn lưu dạng chuẩn hóa: cắt khoảng trắng, chữ thường (CR-05). */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::HoatDong;
    }

    public function hasTwoFactorConfirmed(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Vai đang gán (đọc một lần mỗi yêu cầu; vai đổi có hiệu lực từ yêu cầu kế tiếp, 04 §1).
     *
     * @return list<RoleCode>
     */
    public function roleCodes(): array
    {
        if ($this->roleCodesCache === null) {
            $codes = $this->roles()->pluck('code')->all();
            $this->roleCodesCache = array_values(array_map(
                fn ($c) => $c instanceof RoleCode ? $c : RoleCode::from((string) $c),
                $codes,
            ));
        }

        return $this->roleCodesCache;
    }

    public function forgetRoleCache(): void
    {
        $this->roleCodesCache = null;
        $this->unsetRelation('roles');
    }

    public function hasRole(RoleCode $code): bool
    {
        return in_array($code, $this->roleCodes(), true);
    }

    /**
     * Vai hiệu lực (04 §1): tài khoản hoạt động; vai Giáo vụ/Quản trị cần 2FA đã xác nhận.
     * CR-187: bằng chứng 2FA gắn tài khoản — phiên mở trước khi gán vai vẫn dùng được.
     *
     * @return list<RoleCode>
     */
    public function effectiveRoles(): array
    {
        if (! $this->isActive()) {
            return [];
        }

        return array_values(array_filter(
            $this->roleCodes(),
            fn (RoleCode $c) => ! $c->requiresTwoFactor() || $this->hasTwoFactorConfirmed(),
        ));
    }

    public function hasEffectiveRole(RoleCode ...$codes): bool
    {
        return array_intersect(
            array_map(fn (RoleCode $c) => $c->value, $codes),
            array_map(fn (RoleCode $c) => $c->value, $this->effectiveRoles()),
        ) !== [];
    }

    /** Có vai nhân sự đã gán nhưng chưa hiệu lực vì thiếu 2FA. */
    public function hasPendingStaffRole(RoleCode ...$codes): bool
    {
        if ($this->hasTwoFactorConfirmed()) {
            return false;
        }
        $codes = $codes === [] ? [RoleCode::GiaoVu, RoleCode::QuanTri] : $codes;
        foreach ($codes as $code) {
            if ($code->requiresTwoFactor() && $this->hasRole($code)) {
                return true;
            }
        }

        return false;
    }

    /** Chỉ có vai nhân sự (không có vai nào dùng được khi chưa 2FA) → mọi màn dẫn tới thiết lập 2FA (A6). */
    public function onlyPendingStaffRoles(): bool
    {
        return $this->hasPendingStaffRole() && $this->effectiveRoles() === [];
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }
}
