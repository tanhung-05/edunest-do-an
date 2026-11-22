<?php

namespace App\Actions\M07;

use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderStatus;
use App\Jobs\M07\GrantOrder;
use App\Models\M07\Order;
use App\Support\M07\OrderLocks;
use App\Support\M07\OrderQueue;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EnsureGrantJob
{
    public function handle(int $orderId): void
    {
        Tx::run(fn () => $this->locked(OrderLocks::order($orderId)));
    }

    /** TV-01: ghi vé và hàng đợi cùng giao dịch, dưới khóa đơn. */
    public function locked(Order $order, bool $consumed = false, bool $replace = false): void
    {
        if ($order->status !== OrderStatus::DangCapQuyen || $order->grant_stopped_at !== null) {
            return;
        }

        $pending = $order->items()->where('status', OrderItemStatus::ChuaCap)->get();
        $now = now();
        if ($pending->isEmpty() || $pending->contains(fn ($item) => $item->grant_run_expires_at?->gt($now) === true)) {
            return;
        }

        if (! $consumed && ! $replace && $order->grant_ticket !== null && $order->grant_job_id !== null) {
            $alive = DB::table(config()->string('queue.connections.database.table'))->where('id', $order->grant_job_id)
                ->where('queue', 'grants')->where(fn ($q) => $q->whereNull('reserved_at')
                ->orWhere('reserved_at', '>', $now->getTimestamp() - config()->integer('queue.connections.database.retry_after')))
                ->exists();
            if ($alive) {
                return;
            }
        }

        $ticket = $consumed && $order->grant_ticket !== null ? $order->grant_ticket : (string) Str::ulid();
        $next = $pending->min(fn ($item): CarbonImmutable => $item->grant_next_attempt_at ?? $now);
        $job = (new GrantOrder($order->id, $ticket))->beforeCommit();
        $queue = OrderQueue::connection();
        $jobId = $next !== null && $next->gt($now)
            ? $queue->later($next->getTimestamp() + (int) ($next->micro > 0) - $now->getTimestamp(), $job, '', 'grants')
            : $queue->push($job, '', 'grants');

        $order->forceFill([
            'grant_ticket' => $ticket,
            'grant_ticket_at' => $consumed ? $order->grant_ticket_at : $now,
            'grant_job_id' => (int) $jobId,
        ])->save();
    }
}
