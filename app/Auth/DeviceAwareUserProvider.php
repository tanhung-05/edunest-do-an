<?php

namespace App\Auth;

use App\Models\M01\UserDevice;
use App\Models\User;
use App\Support\M09\LearningFile;
use App\Support\M16\DeviceRegistry;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * BR-TB-18: token "ghi nhớ đăng nhập" của học viên lưu theo thiết bị (`user_devices.remember_token_hash`),
 * nên đăng xuất từ xa một thiết bị thu hồi đúng token của thiết bị đó (E21). Nhân sự giữ cơ chế mặc định.
 */
final class DeviceAwareUserProvider extends EloquentUserProvider
{
    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        $user = $this->newModelQuery()->whereKey($identifier)->first();
        if (! $user instanceof User || ! DeviceRegistry::limited($user)) {
            return parent::retrieveByToken($identifier, $token);
        }
        $device = $this->currentDevice($user);
        if ($device === null || $device->remember_token_hash === null || ! hash_equals($device->remember_token_hash, hash('sha256', (string) $token))) {
            return null;
        }
        $user->useDeviceRememberToken((string) $token);

        return $user;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token)
    {
        if (! $user instanceof User || ! DeviceRegistry::limited($user)) {
            parent::updateRememberToken($user, $token);

            return;
        }
        // Đăng xuất xoay token: chỉ đổi token của thiết bị hiện tại, không đổi token chung của tài khoản.
        $this->currentDevice($user)?->update(['remember_token_hash' => hash('sha256', (string) $token)]);
        $user->useDeviceRememberToken((string) $token);
    }

    private function currentDevice(User $user): ?UserDevice
    {
        $cookie = DeviceRegistry::cookieIfPresent(request());

        return $cookie === null ? null : UserDevice::query()->where('user_id', $user->id)
            ->where('cookie_hash', LearningFile::cookieHash($user, $cookie))->whereNull('revoked_at')->first();
    }
}
