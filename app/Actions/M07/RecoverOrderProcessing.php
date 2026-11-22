<?php

namespace App\Actions\M07;

use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentEventOutcome;
use App\Enums\M07\PaymentEventResult;
use App\Enums\M07\PaymentTxnStatus;
use App\Models\M07\Order;
use App\Support\DomainRejection;
use App\Support\M07\GrantRun;
use App\Support\M07\OrderLocks;
use App\Support\M07\PaymentConfirmation;
use App\Support\TestBarrier;
use App\Support\Tx;

final class RecoverOrderProcessing
{
    public function handle(int $orderId, ?GrantRun $run = null): void
    {
        Tx::run(function () use ($orderId, $run) {
            $order = OrderLocks::order($orderId);
            if ($run !== null && ! GrantRun::ownsJob($order, $run->ticket, $run->jobId)) {
                return;
            }
            OrderLocks::users($order);
            TestBarrier::reach('recover-order-locked');
            $this->locked($order, $run);
        });
    }

    /** E8 / TV-02: phục hồi phần ghi thiếu trước, sau đó xếp lại hoặc chốt. */
    public function locked(Order $order, ?GrantRun $run = null, ?PaymentConfirmation $confirmation = null, ?int $paymentId = null): void
    {
        app(ExpireOrder::class)->locked($order);
        $unreconciled = $order->payments()->where('txn_status', PaymentTxnStatus::ThanhCong)
            ->where(fn ($q) => $q->whereNull('received_amount')->orWhereNull('reconcile_status'))->get();
        foreach ($unreconciled as $payment) {
            $original = $payment->events()->where('result', PaymentEventResult::ThanhCong)
                ->where('signature_valid', true)->where('outcome', PaymentEventOutcome::DaGhi)->first();
            $amount = null;
            if ($original !== null) {
                try {
                    $amount = PaymentConfirmation::parse($original->payload)->amount;
                } catch (DomainRejection) {
                    // Bản chụp lỗi không được dùng để suy ra số thực thu.
                }
            }
            $amount ??= $paymentId === $payment->id ? $confirmation?->amount : null;
            if ($payment->reconcile_status !== null) {
                if ($payment->received_amount === null && $amount !== null) {
                    $payment->update(['received_amount' => $amount]);
                }

                continue;
            }
            app(ReconcilePayment::class)->recordSuccess($order, $payment, $amount);
        }

        if ($order->status !== OrderStatus::DangCapQuyen || $order->grant_stopped_at !== null) {
            return;
        }

        $replace = false;
        $consumed = false;
        $now = now();
        $running = $order->items()->whereNotNull('grant_run_token')->get();
        if ($run !== null && ! $running->contains(fn ($item) => $item->id === $run->itemId && $item->grant_run_token === $run->token
            && $item->grant_run_expires_at === null)) {
            return;
        }
        foreach ($running as $item) {
            if ($item->grant_run_expires_at === null) {
                $item->update(['grant_run_token' => null]);
                $consumed = true;
            } elseif ($item->grant_run_expires_at->lte($now) && $item->status === OrderItemStatus::ChuaCap) {
                app(MarkOrderItemGrantFailed::class)->locked($item, 'Lần chạy cấp quyền hết thời hạn giữ việc.', $item->grant_run_expires_at);
                $item->update(['grant_run_token' => null]);
                $replace = true;
            }
        }

        if ($order->items()->where('status', OrderItemStatus::ChuaCap)->exists()) {
            app(EnsureGrantJob::class)->locked($order, $consumed, $replace);
        } else {
            app(SettleOrder::class)->locked($order);
        }
    }
}
