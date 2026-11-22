<?php

namespace App\Events\M07;

use App\Models\M07\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class OrderNeedsAttention implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
