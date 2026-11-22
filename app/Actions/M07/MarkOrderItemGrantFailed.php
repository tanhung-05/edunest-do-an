<?php

namespace App\Actions\M07;

use App\Enums\M07\OrderItemStatus;
use App\Models\M07\OrderItem;
use App\Models\M11\Setting;
use App\Support\Audit;
use App\Support\M07\GrantRun;
use App\Support\M07\OrderLocks;
use App\Support\Tx;
use Carbon\CarbonImmutable;

final class MarkOrderItemGrantFailed
{
    public const BACKOFF = [60, 300, 900];

    public function handle(GrantRun $run, string $error): bool
    {
        return Tx::run(function () use ($run, $error) {
            $item = $run->current(OrderLocks::order($run->orderId));
            if ($item === null) {
                return false;
            }

            $this->locked($item, $error, now());

            return true;
        });
    }

    /** Cả người chạy và tác vụ phục hồi ghi lỗi dưới cùng khóa đơn. */
    public function locked(OrderItem $item, string $error, CarbonImmutable $at): void
    {
        $last = $item->grant_attempts >= Setting::number('grant.tries');
        $delay = self::BACKOFF[$item->grant_attempts - 1] ?? 900;
        $item->update([
            'status' => $last ? OrderItemStatus::CapLoi : OrderItemStatus::ChuaCap,
            'grant_last_error' => mb_substr($error, 0, 500),
            'grant_next_attempt_at' => $last ? null : $at->addSeconds($delay)->ceilSecond(),
            'grant_run_expires_at' => null,
        ]);
        if ($last) {
            // AC-08-64: lịch sử đơn có mục "cấp lỗi" do hệ thống ghi.
            Audit::log('ORDER_ITEM_GRANT_FAILED', null, $item, ['status' => 'CHUA_CAP'], ['status' => 'CAP_LOI'], $item->grant_last_error);
        }
    }
}
