<?php

namespace App\Actions\M07;

use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentChannel;
use App\Enums\M07\PaymentEventOutcome;
use App\Enums\M07\PaymentEventResult;
use App\Enums\M07\PaymentReconcileStatus;
use App\Enums\M07\PaymentTxnStatus;
use App\Events\M07\OrderNeedsAttention;
use App\Events\M07\PaymentReconciled;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use App\Models\M07\PaymentEvent;
use App\Support\M07\OrderLocks;
use App\Support\M07\OrderPayments;
use App\Support\M07\PaymentConfirmation;
use App\Support\M12\CouponSlots;
use App\Support\TestBarrier;
use App\Support\Tx;
use Illuminate\Database\QueryException;

final class ReconcilePayment
{
    /**
     * Bước ghi và đối soát chung (QT-07 bước 10–12). P8 M28 dùng lại cho chuyển khoản: kênh của khoản phải khớp `$channel`;
     * `$signatureValid` do adapter kênh tự kiểm bằng khóa của nguồn (QT-30 bước 5).
     */
    public function handle(string $payload, ?string $signature, PaymentChannel $channel = PaymentChannel::MoPhong, ?bool $signatureValid = null): PaymentEvent
    {
        $confirmation = PaymentConfirmation::parse($payload);
        $receivedAt = now();
        TestBarrier::reach('reconcile-payment-received');
        $valid = $signatureValid ?? ($signature !== null && hash_equals(PaymentConfirmation::sign($payload), $signature));

        for ($attempt = 1; ; $attempt++) {
            try {
                return Tx::run(function () use ($confirmation, $payload, $signature, $valid, $receivedAt, $channel) {
                    $payment = Payment::query()->where('transaction_code', $confirmation->code)->first();
                    $order = null;
                    if (! $valid) {
                        $outcome = PaymentEventOutcome::TuChoiChuKy;
                    } elseif ($payment === null || $payment->channel !== $channel) {
                        $outcome = PaymentEventOutcome::TuChoiMaLa;
                    } else {
                        $order = OrderLocks::order($payment->order_id);
                        OrderLocks::users($order);
                        TestBarrier::reach('reconcile-payment-locked');
                        $payment = OrderPayments::forOrder($order, $payment->id);
                        app(ExpireOrder::class)->locked($order);
                        $outcome = $this->process($order, $payment, $confirmation);
                    }

                    $event = PaymentEvent::query()->create([
                        'payment_id' => $order === null ? null : $payment->id,
                        'transaction_code' => $confirmation->code,
                        'result' => $confirmation->result,
                        'signature_valid' => $valid,
                        'payload' => $payload,
                        'signature' => $signature === null ? null : substr($signature, 0, 255),
                        'outcome' => $outcome,
                        'received_at' => $receivedAt,
                    ]);

                    if ($order !== null) {
                        if ($outcome === PaymentEventOutcome::TrungLap) {
                            $trusted = $confirmation->result === PaymentEventResult::ThanhCong ? $confirmation : null;
                            app(RecoverOrderProcessing::class)->locked($order, confirmation: $trusted, paymentId: $payment->id);
                        } elseif ($payment->reconcile_status === PaymentReconcileStatus::HopLe) {
                            app(EnsureGrantJob::class)->locked($order);
                        }
                    }

                    return $event;
                });
            } catch (QueryException $e) {
                if ($attempt >= 2 || Tx::code($e) !== 1062 || ! str_contains($e->getMessage(), 'payment_events_transaction_code_result_accepted_key_unique')) {
                    throw $e;
                }
            }
        }
    }

    private function process(Order $order, Payment $payment, PaymentConfirmation $confirmation): PaymentEventOutcome
    {
        $duplicate = $payment->events()->where('result', $confirmation->result)
            ->where('outcome', PaymentEventOutcome::DaGhi)->exists();
        if ($duplicate || $payment->txn_status === PaymentTxnStatus::ThanhCong) {
            return PaymentEventOutcome::TrungLap;
        }

        if ($confirmation->result === PaymentEventResult::ThatBai) {
            if ($payment->txn_status === PaymentTxnStatus::ThatBai) {
                return PaymentEventOutcome::TrungLap;
            }
            $payment->update(['txn_status' => PaymentTxnStatus::ThatBai, 'failed_at' => now()]);
        } else {
            $this->recordSuccess($order, $payment, $confirmation->amount);
        }

        return PaymentEventOutcome::DaGhi;
    }

    /** GD-2/4, DS-1/2 và cạnh đơn tương ứng. Đơn và học viên đã khóa. */
    public function recordSuccess(Order $order, Payment $payment, ?int $amount): void
    {
        $now = now();
        $amount = $payment->received_amount ?? $amount;
        if ($amount === null) {
            return;
        }
        $from = $order->status;
        $terminal = in_array($from, [OrderStatus::HetHan, OrderStatus::DaHuy], true);
        $hasValid = OrderPayments::hasValid($order);
        $late = $terminal || ($order->status_before_cxl !== null && ! $hasValid);
        $valid = $from === OrderStatus::ChoThanhToan && $amount === $order->total && ! $hasValid && $order->grant_stopped_at === null
            && ! OrderPayments::laterTransfer($order, $payment);
        $payment->update([
            'txn_status' => PaymentTxnStatus::ThanhCong,
            'reconcile_status' => $valid ? PaymentReconcileStatus::HopLe : PaymentReconcileStatus::CanHoan,
            'received_amount' => $amount,
            'is_late' => $late,
            'succeeded_at' => $payment->succeeded_at ?? $now,
            'reconciled_at' => $now,
        ]);

        if ($valid) {
            $order->update(['status' => OrderStatus::DangCapQuyen]);
            CouponSlots::consume($order);
            PaymentReconciled::dispatch(clone $payment);
        } else {
            if ($terminal || $from === OrderStatus::HoanTat) {
                $order->update([
                    'status' => OrderStatus::CanXuLy,
                    'status_before_cxl' => $terminal ? $from : null,
                ]);
            }
            OrderNeedsAttention::dispatch(clone $order);
        }
    }
}
