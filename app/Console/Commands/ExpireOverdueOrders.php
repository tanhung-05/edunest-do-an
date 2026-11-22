<?php

namespace App\Console\Commands;

use App\Actions\M07\ExpireOrder;
use App\Enums\M07\OrderStatus;
use App\Models\M07\Order;
use Illuminate\Console\Command;

final class ExpireOverdueOrders extends Command
{
    protected $signature = 'edunest:expire-overdue-orders';

    protected $description = 'Chốt các đơn chờ thanh toán đã quá hạn';

    public function handle(ExpireOrder $expire): int
    {
        Order::query()->where('status', OrderStatus::ChoThanhToan)->where('expires_at', '<', now())
            ->eachById(fn (Order $order) => $expire->handle($order->id));

        return self::SUCCESS;
    }
}
