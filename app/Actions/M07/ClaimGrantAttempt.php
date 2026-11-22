<?php

namespace App\Actions\M07;

use App\Enums\M07\OrderItemStatus;
use App\Models\M11\Setting;
use App\Support\M07\GrantRun;
use App\Support\M07\OrderLocks;
use App\Support\Tx;
use Illuminate\Support\Str;

final class ClaimGrantAttempt
{
    public function handle(int $orderId, string $ticket, int $jobId): ?GrantRun
    {
        return Tx::run(function () use ($orderId, $ticket, $jobId) {
            $order = OrderLocks::order($orderId);
            if (! GrantRun::ownsJob($order, $ticket, $jobId)) {
                return null;
            }

            $pending = $order->items()->where('status', OrderItemStatus::ChuaCap)->reorder('id')->get();
            $now = now();
            // TV-02 ghi lỗi hết quyền trước khi phát vé thay thế.
            if ($order->items()->whereNotNull('grant_run_token')->exists()) {
                return null;
            }

            $item = $pending->first(fn ($item) => $item->grant_next_attempt_at === null || $item->grant_next_attempt_at->lte($now));
            if ($item === null || $item->grant_attempts >= Setting::number('grant.tries')) {
                return null;
            }

            $token = (string) Str::ulid();
            $lease = min(75, config()->integer('queue.connections.database.retry_after'));
            $item->update([
                'grant_attempts' => $item->grant_attempts + 1,
                'grant_last_attempt_at' => $now,
                'grant_run_token' => $token,
                'grant_run_expires_at' => $now->addSeconds($lease),
            ]);

            return new GrantRun($orderId, $ticket, $jobId, $item->id, $token);
        });
    }
}
