<?php

namespace App\Actions\M07;

use App\Enums\M07\PaymentEventResult;
use App\Enums\M07\PaymentTxnStatus;
use App\Models\M07\Payment;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M07\PaymentConfirmation;
use App\Support\M07\SimulatedConfirmations;
use App\Support\Tx;

final class SendSimulatedPaymentConfirmation
{
    public function handle(User $actor, Payment $payment, PaymentEventResult $result): string
    {
        return Tx::run(function () use ($actor, $payment, $result) {
            $payment = SimulatedConfirmations::lock($actor, $payment);
            $original = SimulatedConfirmations::original($payment);
            if ($original !== null) {
                return $original;
            }
            if ($payment->txn_status !== PaymentTxnStatus::KhoiTao) {
                throw new DomainRejection('Khoản thanh toán đã có kết quả.');
            }

            $payload = PaymentConfirmation::simulated($payment, $result);
            SimulatedConfirmations::enqueue($payment, $payload);

            return $payload;
        });
    }
}
