<?php

namespace App\Console\Commands;

use App\Actions\M07\RecoverOrderProcessing;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentTxnStatus;
use App\Models\M07\Order;
use Illuminate\Console\Command;

final class RecoverGrantingOrders extends Command
{
    protected $signature = 'edunest:recover-granting-orders';

    protected $description = 'Khôi phục cấp quyền và đối soát còn dở';

    public function handle(RecoverOrderProcessing $recover): int
    {
        Order::query()->where(fn ($q) => $q->where('status', OrderStatus::DangCapQuyen)
            ->orWhereHas('payments', fn ($p) => $p->where('txn_status', PaymentTxnStatus::ThanhCong)
                ->where(fn ($incomplete) => $incomplete->whereNull('received_amount')->orWhereNull('reconcile_status'))))
            ->eachById(fn (Order $order) => $recover->handle($order->id));

        return self::SUCCESS;
    }
}
