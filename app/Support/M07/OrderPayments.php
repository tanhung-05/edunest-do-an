<?php

namespace App\Support\M07;

use App\Enums\M07\PaymentChannel;
use App\Enums\M07\PaymentReconcileStatus;
use App\Models\M07\Order;
use App\Models\M07\Payment;

final class OrderPayments
{
    /** CR-178: chuyển khoản không phải lần thứ nhất theo thứ tự nguồn của đơn luôn `CAN_HOAN`, QT-08 không nâng lên `HOP_LE`. */
    public static function laterTransfer(Order $order, Payment $payment): bool
    {
        return $payment->channel === PaymentChannel::ChuyenKhoan && $payment->source_seq !== null
            && Payment::query()->where('order_id', $order->id)->where('channel', PaymentChannel::ChuyenKhoan->value)
                ->whereNotNull('source_seq')->where('source_seq', '<', $payment->source_seq)->exists();
    }

    public static function hasValid(Order $order): bool
    {
        return $order->payments()->where('reconcile_status', PaymentReconcileStatus::HopLe)->exists();
    }

    public static function forOrder(Order $order, int $paymentId): Payment
    {
        return $order->payments()->whereKey($paymentId)->firstOrFail();
    }
}
