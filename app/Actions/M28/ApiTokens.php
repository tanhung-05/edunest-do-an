<?php

namespace App\Actions\M28;

use App\Enums\M01\RoleCode;
use App\Models\M01\UserDevice;
use App\Models\M11\Setting;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M16\DeviceRegistry;
use App\Support\Tx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * M28 API di động (02e §6): token Sanctum gắn một thiết bị trong `user_devices` (M16). Cấp token tính vào giới hạn thiết bị
 * như đăng nhập web, dưới khóa dòng người dùng; đăng xuất thiết bị (web hoặc API) xóa token của thiết bị đó.
 */
final class ApiTokens
{
    /** @return array{token: string, device_id: int} */
    public function issue(string $email, string $password, string $deviceName): array
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();
        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new DomainRejection('Email hoặc mật khẩu không đúng.', 'email');
        }

        return Tx::run(function () use ($user, $deviceName) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isActive() || ! $locked->hasEffectiveRole(RoleCode::HocVien)) {
                throw DomainRejection::forbidden('API chỉ dành cho tài khoản Học viên đang hoạt động.');
            }
            if ($locked->must_change_password) {
                throw DomainRejection::forbidden('Hãy đổi mật khẩu trên web trước khi dùng ứng dụng.');
            }
            if (DeviceRegistry::limited($locked) && DeviceRegistry::active($locked)->count() >= Setting::number('device.max_active')) {
                throw new DomainRejection('Đã đạt giới hạn thiết bị. Đăng xuất một thiết bị ở trang Thiết bị của tôi rồi thử lại.', 'device_name', 409);
            }
            $now = now();
            $device = UserDevice::query()->create([
                'user_id' => $locked->id, 'cookie_hash' => hash('sha256', 'api:'.$locked->id.':'.Str::random(40)),
                'browser' => 'Ứng dụng EduNest', 'os' => mb_substr(trim($deviceName), 0, 60), 'first_seen_at' => $now, 'last_active_at' => $now,
            ]);
            $token = $locked->createToken(mb_substr(trim($deviceName), 0, 100));
            $token->accessToken->forceFill(['user_device_id' => $device->id])->save();

            return ['token' => $token->plainTextToken, 'device_id' => $device->id];
        });
    }

    /** Đăng xuất ứng dụng: thu hồi thiết bị của token hiện tại (kéo theo token). */
    public function revoke(User $user, PersonalAccessToken $token): void
    {
        $deviceId = $token->getAttribute('user_device_id');
        if (is_int($deviceId)) {
            DeviceRegistry::revoke($user, $deviceId);
        }
        DB::table('personal_access_tokens')->where('id', $token->id)->delete();
    }
}
