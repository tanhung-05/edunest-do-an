<?php

namespace App\Actions\M07;

use App\Actions\M24\AffiliateCommissions;
use App\Enums\M07\OrderCancelReason;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentReconcileStatus;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M07\OrderPayments;
use App\Support\M12\CouponSlots;
use App\Support\Tx;

final class MarkPaymentRefunded
{
    public function handle(User $actor, Order $order, Payment $payment, string $reason, bool $confirmed): Payment
    {
        $result = Tx::run(function () use ($actor, $order, $payment, $reason, $confirmed) {
            $order = OrderLocks::order($order->id);
            OrderLocks::academic(OrderLocks::users($order, $actor));
            $payment = OrderPayments::forOrder($order, $payment->id);
            app(ExpireOrder::class)->locked($order);
            if ($payment->reconcile_status !== PaymentReconcileStatus::CanHoan) {
                return new DomainRejection('Trạng thái khoản đã đổi. Hãy tải lại đơn.');
            }
            if (trim($reason) === '' || mb_strlen($reason) > 500) {
                return new DomainRejection('Nhập lý do hoàn khoản từ 1 đến 500 ký tự.', 'reason');
            }
            if (! $confirmed) {
                return new DomainRejection('Hãy xác nhận đã hoàn khoản.', 'confirmed');
            }

            $payment->update([
                'reconcile_status' => PaymentReconcileStatus::DaHoanLai,
                'refunded_at' => now(), 'refunded_by_id' => $actor->id, 'refund_reason' => trim($reason),
            ]);
            if ($order->grant_refund_payment_id === $payment->id) {
                CouponSlots::giveBack($order);
            }
            // QT-26 A6: hoàn khoản của đơn có nguồn giới thiệu → hủy hoa hồng cùng lần ghi.
            AffiliateCommissions::cancelForRefund($order, 'Hoàn khoản: '.trim($reason));
            Audit::log('PAYMENT_MARK_REFUNDED', $actor, $payment, ['reconcile_status' => 'CAN_HOAN'],
                ['reconcile_status' => 'DA_HOAN_LAI', 'received_amount' => $payment->received_amount], trim($reason));

            if ($order->status === OrderStatus::CanXuLy && $order->grant_stopped_at !== null
                && ! $order->payments()->where('reconcile_status', PaymentReconcileStatus::CanHoan)->exists()) {
                // DH-16 (CR-133): hoàn đủ mọi khoản của đơn đã chọn hoàn → DA_HUY, lý do "hủy sau hoàn".
                $order->update(['status' => OrderStatus::DaHuy, 'cancelled_at' => now(), 'cancel_reason' => OrderCancelReason::HuySauHoan]);
            } elseif ($order->status === OrderStatus::CanXuLy && $order->grant_stopped_at === null) {
                $latePending = $order->payments()->where('is_late', true)->where('reconcile_status', PaymentReconcileStatus::CanHoan)->exists();
                if ($order->status_before_cxl !== null && ! $latePending && ! OrderPayments::hasValid($order)) {
                    $order->update(['status' => $order->status_before_cxl, 'status_before_cxl' => null]);
                } else {
                    app(SettleOrder::class)->locked($order);
                }
            }

            return $payment;
        });

        if ($result instanceof DomainRejection) {
            throw $result;
        }

        return $result;
    }
}
