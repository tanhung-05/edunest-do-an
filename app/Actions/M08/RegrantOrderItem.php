<?php

namespace App\Actions\M08;

use App\Actions\M07\SettleOrder;
use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderStatus;
use App\Models\M07\Order;
use App\Models\M07\OrderItem;
use App\Models\M11\Setting;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M07\OrderPayments;
use App\Support\Tx;
use Illuminate\Database\QueryException;

final class RegrantOrderItem
{
    public function handle(User $actor, Order $order, OrderItem $item): OrderItem
    {
        try {
            return Tx::run(fn () => $this->regrant($actor, $order->id, $item->id));
        } catch (QueryException $e) {
            report($e);

            // Giao dịch cấp đã rollback. Đọc lại quyền và tình trạng dòng trước khi ghi lỗi DD-5.
            return Tx::run(fn () => $this->regrant($actor, $order->id, $item->id, 'Cấp lại gặp lỗi kỹ thuật.'));
        }
    }

    private function regrant(User $actor, int $orderId, int $itemId, ?string $error = null): OrderItem
    {
        $order = OrderLocks::order($orderId);
        OrderLocks::academic(OrderLocks::users($order, $actor));
        $item = $order->items()->whereKey($itemId)->firstOrFail();
        if ($item->status !== OrderItemStatus::CapLoi || ! in_array($order->status, [OrderStatus::CanXuLy, OrderStatus::DangCapQuyen], true)
            || $order->grant_stopped_at !== null || ! OrderPayments::hasValid($order)) {
            throw new DomainRejection('Dòng đơn không còn đủ điều kiện cấp lại.');
        }

        if (Setting::grantFailureMode()) {
            $error ??= 'Lỗi cấp quyền mô phỏng.';
        }
        if ($error === null) {
            app(GrantOrderItem::class)->locked($order, $item);
        } else {
            $item->update(['grant_last_error' => $error]);
        }

        Audit::log('ORDER_ITEM_REGRANT', $actor, $item, ['status' => 'CAP_LOI'], ['status' => $item->status->value], $item->grant_last_error);
        app(SettleOrder::class)->locked($order);

        return $item;
    }
}
