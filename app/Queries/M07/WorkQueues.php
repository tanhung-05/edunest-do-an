<?php

namespace App\Queries\M07;

use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentReconcileStatus;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use Illuminate\Database\Eloquent\Builder;

final class WorkQueues
{
    /** @return Builder<Order> */
    public static function orders(): Builder
    {
        return Order::query()->where('status', OrderStatus::CanXuLy)->with(['items', 'user'])->latest('id');
    }

    /** @return Builder<Payment> */
    public static function refunds(): Builder
    {
        return Payment::query()->where('reconcile_status', PaymentReconcileStatus::CanHoan)->with('order.payments')->latest('id');
    }
}
