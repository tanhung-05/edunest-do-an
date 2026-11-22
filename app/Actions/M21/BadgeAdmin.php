<?php

namespace App\Actions\M21;

use App\Enums\M01\RoleCode;
use App\Enums\M21\BadgeRule;
use App\Models\M21\Badge;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\Tx;
use Illuminate\Support\Str;

/** QT-23 A1a/A1b: Quản trị định nghĩa huy hiệu; sửa giữ định danh và các lần đã trao (BR-XP-71, CR-232). */
final class BadgeAdmin
{
    /** @param array{name: string, description: ?string, rule: string, threshold: int, is_active: bool} $data */
    public function save(User $actor, array $data, ?Badge $badge = null): Badge
    {
        return Tx::run(function () use ($actor, $data, $badge) {
            $actor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if (! $actor->isActive() || ! $actor->hasEffectiveRole(RoleCode::QuanTri)) {
                throw DomainRejection::forbidden('Chỉ Quản trị định nghĩa huy hiệu.');
            }
            $fields = ['name' => $data['name'], 'description' => $data['description'], 'rule' => BadgeRule::from($data['rule']),
                'threshold' => $data['threshold'], 'is_active' => $data['is_active']];
            if ($badge === null) {
                $badge = Badge::query()->create($fields + ['code' => 'HH_'.Str::upper(Str::random(8))]);
                Audit::log('BADGE_CREATE', $actor, $badge, null, $data);
            } else {
                $badge = Badge::query()->whereKey($badge->id)->lockForUpdate()->firstOrFail();
                $old = $badge->only(['name', 'description', 'rule', 'threshold', 'is_active']);
                $badge->fill($fields)->save();
                Audit::log('BADGE_UPDATE', $actor, $badge, $old, $data);
            }

            return $badge;
        });
    }
}
