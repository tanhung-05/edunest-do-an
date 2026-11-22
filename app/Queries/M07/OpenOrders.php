<?php

namespace App\Queries\M07;

use App\Enums\M07\OrderItemKind;
use App\Enums\M07\OrderStatus;
use App\Models\M07\Order;
use Illuminate\Database\Eloquent\Builder;

final class OpenOrders
{
    /**
     * @param  list<int>  $courseIds
     * @return Builder<Order>
     */
    public static function forCourses(int $userId, array $courseIds, bool $purchaseOnly = false): Builder
    {
        return Order::query()->where('user_id', $userId)
            ->whereIn('status', [OrderStatus::ChoThanhToan, OrderStatus::DangCapQuyen, OrderStatus::CanXuLy])
            ->whereHas('items', fn ($items) => $items->whereIn('course_id', $courseIds)
                ->when($purchaseOnly, fn ($q) => $q->where('kind', OrderItemKind::MuaKhoa)));
    }

    /** @param list<int> $courseIds */
    public static function current(int $userId, array $courseIds): ?Order
    {
        $now = now();

        return self::forCourses($userId, $courseIds)
            ->where(fn ($q) => $q->where('status', '<>', OrderStatus::ChoThanhToan)->orWhere('expires_at', '>=', $now->format('Y-m-d H:i:s')))
            ->orderBy('id')->get()->first(fn (Order $order) => $order->status !== OrderStatus::ChoThanhToan || $now->lte($order->expires_at));
    }
}
