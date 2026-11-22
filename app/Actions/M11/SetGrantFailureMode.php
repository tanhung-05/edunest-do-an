<?php

namespace App\Actions\M11;

use App\Enums\M01\RoleCode;
use App\Models\M11\Setting;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\Tx;

final class SetGrantFailureMode
{
    public function handle(User $actor, bool $enabled): Setting
    {
        return Tx::run(function () use ($actor, $enabled) {
            $user = User::query()->whereKey($actor->id)->sharedLock()->firstOrFail();
            if (! config()->boolean('app.demo', false) || ! $user->hasEffectiveRole(RoleCode::QuanTri) || ! $user->isActive()) {
                throw DomainRejection::forbidden('Cấu hình này chỉ dành cho Quản trị trong môi trường demo.');
            }

            $key = 'grant.fault_mode';
            // Khóa duy nhất bảo vệ cả lần cấu hình đầu tiên khi chưa được seed.
            Setting::query()->insertOrIgnore(['key' => $key, 'value' => 'false', 'created_at' => now(), 'updated_at' => now()]);
            $setting = Setting::query()->where('key', $key)->lockForUpdate()->firstOrFail();
            $old = $setting->value;
            if ($old !== $enabled) {
                $setting->update(['value' => $enabled, 'updated_by_id' => $user->id]);
                Audit::log('GRANT_FAILURE_MODE_SET', $user, $setting, ['value' => $old], ['value' => $enabled]);
            }

            return $setting;
        });
    }
}
