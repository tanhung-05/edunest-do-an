<?php

namespace App\Actions\M07;

use App\Models\M07\Payment;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M07\SimulatedConfirmations;
use App\Support\Tx;

final class ResendPaymentConfirmation
{
    public function handle(User $actor, Payment $payment): void
    {
        Tx::run(function () use ($actor, $payment) {
            $payment = SimulatedConfirmations::lock($actor, $payment);
            $original = SimulatedConfirmations::original($payment);
            if ($original === null) {
                throw new DomainRejection('Khoản này chưa có xác nhận gốc để gửi lại.');
            }

            SimulatedConfirmations::enqueue($payment, $original);
        });
    }
}
