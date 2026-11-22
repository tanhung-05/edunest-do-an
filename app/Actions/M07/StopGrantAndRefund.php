<?php

namespace App\Actions\M07;

use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentReconcileStatus;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M07\OrderPayments;
use App\Support\Tx;

/**
 * CR-133, CR-182, NH-08: Giáo vụ chọn hoàn cho đơn `CAN_XU_LY` đã dừng cấp (không còn `CHUA_CAP`), 0 dòng `DA_CAP`.
 * Khoản `HOP_LE → CAN_HOAN`; khóa Cấp lại và cộng hạn. Hoàn xong mọi khoản thì DH-16 (`MarkPaymentRefunded`).
 */
final class StopGrantAndRefund
{
    public function handle(User $actor, Order $order, Payment $payment, string $reason, bool $confirmed): Order
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainRejection('Nhập lý do từ 1 đến 500 ký tự.', 'reason');
        }
        if (! $confirmed) {
            throw new DomainRejection('Hãy xác nhận chọn hoàn.', 'confirmed');
        }

        return Tx::run(function () use ($actor, $order, $payment, $reason) {
            $order = OrderLocks::order($order->id);
            OrderLocks::academic(OrderLocks::users($order, $actor));
            $payment = OrderPayments::forOrder($order, $payment->id);
            $items = $order->items()->get();
            if ($order->status !== OrderStatus::CanXuLy || $order->grant_stopped_at !== null
                || $items->contains('status', OrderItemStatus::ChuaCap) || $items->contains('status', OrderItemStatus::DaCap)
                || $payment->reconcile_status !== PaymentReconcileStatus::HopLe) {
                throw new DomainRejection('Đơn không đủ điều kiện chọn hoàn (cần đơn cần xử lý, đã dừng cấp, chưa cấp dòng nào).');
            }
            $now = now();
            $payment->update(['reconcile_status' => PaymentReconcileStatus::CanHoan]);
            $order->update([
                'grant_stopped_at' => $now, 'grant_stopped_by_id' => $actor->id, 'grant_stop_reason' => $reason,
                'grant_refund_payment_id' => $payment->id,
            ]);
            Audit::log('ORDER_STOP_GRANT_REFUND', $actor, $order, ['payment_reconcile' => 'HOP_LE'],
                ['payment_reconcile' => 'CAN_HOAN', 'payment_id' => $payment->id], $reason);

            return $order;
        });
    }
}
