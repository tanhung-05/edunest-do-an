<?php

namespace App\Support\M07;

use App\Enums\M01\RoleCode;
use App\Models\M07\Order;
use App\Models\User;
use App\Support\DomainRejection;

final class OrderLocks
{
    public static function order(int $id): Order
    {
        return Order::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** Khóa chủ đơn và người thao tác theo ID, sau khi đã giữ khóa đơn. */
    public static function users(Order $order, ?User $actor = null): ?User
    {
        $users = User::query()->whereKey([$order->user_id, $actor->id ?? $order->user_id])
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        return $actor === null ? null : $users->get($actor->id);
    }

    public static function student(?User $actor, ?int $ownerId = null): void
    {
        if ($actor === null || ! $actor->isActive() || ! $actor->hasEffectiveRole(RoleCode::HocVien)
            || ($ownerId !== null && $actor->id !== $ownerId)) {
            throw DomainRejection::forbidden('Bạn không có quyền thao tác trên đơn này.');
        }
    }

    public static function academic(?User $actor): void
    {
        if ($actor === null || ! $actor->isActive() || ! $actor->hasEffectiveRole(RoleCode::GiaoVu)) {
            throw DomainRejection::forbidden('Thao tác này cần vai Giáo vụ.');
        }
    }
}
