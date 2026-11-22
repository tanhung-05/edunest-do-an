<?php

namespace App\Actions\M07;

use App\Enums\M07\OrderStatus;
use App\Models\M07\Order;
use App\Support\M07\OrderLocks;
use App\Support\M12\CouponSlots;
use App\Support\Tx;

final class ExpireOrder
{
    public function handle(int $orderId): Order
    {
        return Tx::run(function () use ($orderId) {
            $order = OrderLocks::order($orderId);
            OrderLocks::users($order);
            $this->locked($order);

            return $order;
        });
    }

    /** DH-4, gọi dưới khóa đơn và chủ đơn. */
    public function locked(Order $order): bool
    {
        $now = now();
        if ($order->status !== OrderStatus::ChoThanhToan || $now->lte($order->expires_at)) {
            return false;
        }

        $order->update(['status' => OrderStatus::HetHan, 'expired_at' => $now]);
        CouponSlots::release($order);

        return true;
    }
}
