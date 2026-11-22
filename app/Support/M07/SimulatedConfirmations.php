<?php

namespace App\Support\M07;

use App\Enums\M07\PaymentChannel;
use App\Enums\M07\PaymentEventOutcome;
use App\Jobs\M07\SendSimulatedPaymentWebhook;
use App\Models\M07\Payment;
use App\Models\User;
use App\Support\DomainRejection;
use Illuminate\Support\Facades\DB;

final class SimulatedConfirmations
{
    public static function lock(User $actor, Payment $payment): Payment
    {
        $order = OrderLocks::order($payment->order_id);
        OrderLocks::student(OrderLocks::users($order, $actor), $order->user_id);
        $payment = OrderPayments::forOrder($order, $payment->id);
        if ($payment->channel !== PaymentChannel::MoPhong) {
            throw DomainRejection::forbidden('Khoản này không thuộc cổng mô phỏng.');
        }

        return $payment;
    }

    public static function original(Payment $payment): ?string
    {
        // Dữ liệu của TV-04 tồn tại ngay khi commit, kể cả lúc cổng chưa gọi tới webhook.
        $queued = self::storedPayload(config()->string('queue.connections.database.table'), $payment->id);
        if ($queued !== null) {
            return $queued;
        }

        $accepted = $payment->events()->where('outcome', PaymentEventOutcome::DaGhi)->where('signature_valid', true)->first();

        return $accepted->payload ?? self::storedPayload(config()->string('queue.failed.table'), $payment->id);
    }

    private static function storedPayload(string $table, int $paymentId): ?string
    {
        $row = DB::table($table)->where('queue', 'default')
            ->where('payload->edunest_confirmation->payment_id', $paymentId)->orderBy('id')->value('payload');
        if (! is_string($row)) {
            return null;
        }

        $payload = json_decode($row, true)['edunest_confirmation']['body'] ?? null;

        return is_string($payload) ? $payload : null;
    }

    public static function enqueue(Payment $payment, string $payload): void
    {
        $job = (new SendSimulatedPaymentWebhook($payment->id, $payload))->beforeCommit();
        OrderQueue::connection()->push($job, '', 'default');
    }
}
