<?php

namespace App\Actions\M07;

use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentChannel;
use App\Enums\M07\PaymentTxnStatus;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\Tx;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class StartPayment
{
    public function handle(User $actor, Order $order, bool $accepted): Payment
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $payment = Tx::run(fn () => $this->start($actor, $order->id, $accepted));
                break;
            } catch (QueryException $e) {
                if ($attempt >= 3 || Tx::code($e) !== 1062 || ! str_contains($e->getMessage(), 'payments_transaction_code_unique')) {
                    throw $e;
                }
            }
        }

        return $payment ?? throw new DomainRejection('Đơn đã hết hạn.');
    }

    private function start(User $actor, int $orderId, bool $accepted): ?Payment
    {
        $order = OrderLocks::order($orderId);
        $student = OrderLocks::users($order, $actor);
        OrderLocks::student($student, $order->user_id);
        if (! $student?->hasVerifiedEmail()) {
            throw DomainRejection::forbidden('Hãy xác minh email trước khi thanh toán.');
        }
        if (app(ExpireOrder::class)->locked($order)) {
            return null;
        }
        if ($order->status !== OrderStatus::ChoThanhToan) {
            throw new DomainRejection($order->status === OrderStatus::HetHan ? 'Đơn đã hết hạn.' : 'Đơn không còn chờ thanh toán.');
        }
        if (! $accepted) {
            throw new DomainRejection('Hãy đồng ý điều khoản trước khi thanh toán.', 'terms');
        }

        return $order->payments()->create([
            'transaction_code' => 'GD-'.Str::ulid(),
            'requested_amount' => $order->total,
            'channel' => PaymentChannel::MoPhong,
            'txn_status' => PaymentTxnStatus::KhoiTao,
            'terms_accepted_at' => now(),
        ]);
    }
}
