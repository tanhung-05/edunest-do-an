<?php

namespace App\Events\M07;

use App\Models\M07\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class PaymentReconciled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
