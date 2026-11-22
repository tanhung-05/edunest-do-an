<?php

namespace App\Support\M07;

use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderStatus;
use App\Models\M07\Order;
use App\Models\M07\OrderItem;

final readonly class GrantRun
{
    public const TIMEOUT = 45;

    public function __construct(public int $orderId, public string $ticket, public int $jobId, public int $itemId, public string $token) {}

    public static function ownsJob(Order $order, string $ticket, int $jobId): bool
    {
        return $order->status === OrderStatus::DangCapQuyen
            && $order->grant_stopped_at === null
            && $order->grant_ticket === $ticket
            && $order->grant_job_id === $jobId;
    }

    public function current(Order $order): ?OrderItem
    {
        if (! self::ownsJob($order, $this->ticket, $this->jobId)) {
            return null;
        }

        $item = $order->items()->whereKey($this->itemId)->first();

        return $item !== null && $item->status === OrderItemStatus::ChuaCap && $item->grant_run_token === $this->token
            && $item->grant_run_expires_at?->gt(now()) === true ? $item : null;
    }
}
