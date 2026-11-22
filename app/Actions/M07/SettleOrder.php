<?php

namespace App\Actions\M07;

use App\Actions\M24\AffiliateCommissions;
use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentReconcileStatus;
use App\Events\M07\OrderCompleted;
use App\Events\M07\OrderNeedsAttention;
use App\Models\M07\Order;
use App\Support\M07\OrderLocks;
use App\Support\M07\OrderPayments;
use App\Support\Tx;

final class SettleOrder
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

    /** DH-6/7/13, dưới khóa đơn và chủ đơn. */
    public function locked(Order $order): void
    {
        if (! in_array($order->status, [OrderStatus::DangCapQuyen, OrderStatus::CanXuLy], true)
            || $order->grant_stopped_at !== null) {
            return;
        }

        $items = $order->items()->get();
        if ($items->isEmpty() || $items->contains('status', OrderItemStatus::ChuaCap)) {
            return;
        }

        $needsRefund = $order->payments()->where('reconcile_status', PaymentReconcileStatus::CanHoan)->exists();
        if (! $items->contains('status', OrderItemStatus::CapLoi) && ! $needsRefund && OrderPayments::hasValid($order)) {
            $now = now();
            $order->forceFill([
                'status' => OrderStatus::HoanTat, 'completed_at' => $now, 'first_completed_at' => $order->first_completed_at ?? $now,
                'grant_ticket' => null, 'grant_ticket_at' => null, 'grant_job_id' => null,
            ])->save();
            // QT-26 bước 16: hoa hồng chụp ở lần hoàn tất đầu, cùng lần ghi dưới khóa đơn.
            AffiliateCommissions::onFirstCompletion($order);
            OrderCompleted::dispatch(clone $order);
        } elseif ($order->status === OrderStatus::DangCapQuyen && ($needsRefund || $items->contains('status', OrderItemStatus::CapLoi))) {
            $order->forceFill([
                'status' => OrderStatus::CanXuLy, 'grant_ticket' => null, 'grant_ticket_at' => null, 'grant_job_id' => null,
            ])->save();
            OrderNeedsAttention::dispatch(clone $order);
        }
    }
}
