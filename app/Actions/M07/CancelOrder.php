<?php

namespace App\Actions\M07;

use App\Enums\M07\OrderCancelReason;
use App\Enums\M07\OrderStatus;
use App\Models\M07\Order;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M12\CouponSlots;
use App\Support\TestBarrier;
use App\Support\Tx;

final class CancelOrder
{
    public function handle(User $actor, Order $order): Order
    {
        $result = Tx::run(function () use ($actor, $order) {
            $order = OrderLocks::order($order->id);
            OrderLocks::student(OrderLocks::users($order, $actor), $order->user_id);
            TestBarrier::reach('cancel-order-locked');
            if (app(ExpireOrder::class)->locked($order)) {
                return null;
            }
            if ($order->status !== OrderStatus::ChoThanhToan) {
                throw new DomainRejection('Đơn không còn chờ thanh toán.');
            }

            $order->update(['status' => OrderStatus::DaHuy, 'cancelled_at' => now(), 'cancel_reason' => OrderCancelReason::HocVienHuy]);
            CouponSlots::release($order);
            Audit::log('ORDER_CANCEL', $actor, $order, ['status' => OrderStatus::ChoThanhToan->value], ['status' => $order->status->value]);

            return $order;
        });

        return $result ?? throw new DomainRejection('Đơn đã hết hạn.');
    }
}
