<?php

namespace App\Actions\M07;

use App\Enums\M07\OrderItemKind;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentReconcileStatus;
use App\Models\M07\Order;
use App\Models\M07\OrderItem;
use App\Models\M07\Payment;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Queries\M07\OpenOrders;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M07\OrderPayments;
use App\Support\M12\CouponSlots;
use App\Support\TestBarrier;
use App\Support\Tx;

final class AcceptLatePayment
{
    public function handle(User $actor, Order $order, Payment $payment): Payment
    {
        return Tx::run(function () use ($actor, $order, $payment) {
            $order = OrderLocks::order($order->id);
            OrderLocks::academic(OrderLocks::users($order, $actor));
            $payment = OrderPayments::forOrder($order, $payment->id);
            TestBarrier::reach('accept-late-payment-locked');

            if ($order->status !== OrderStatus::CanXuLy || $order->status_before_cxl === null || $order->grant_stopped_at !== null
                || OrderPayments::hasValid($order) || $payment->reconcile_status !== PaymentReconcileStatus::CanHoan
                || ! $payment->is_late || $payment->received_amount !== $order->total || OrderPayments::laterTransfer($order, $payment)) {
                throw new DomainRejection('Khoản thu muộn không đủ điều kiện cấp quyền.');
            }

            $items = $order->items()->get();
            $courseIds = array_values($items->map(fn (OrderItem $item) => $item->course_id)->all());
            // CR-185: đọc thường dưới gác học viên, kể cả đơn chờ đã đến lượt quét.
            if (($blocking = OpenOrders::forCourses($order->user_id, $courseIds)->whereKeyNot($order->id)->first()) !== null) {
                throw new DomainRejection('Đơn '.$blocking->public_code.' đang mở cho khóa này. Hãy tải lại sau khi xử lý đơn đó.');
            }
            $existing = Enrollment::query()->where('user_id', $order->user_id)->whereIn('course_id', $courseIds)
                ->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty() || $existing->isNotEmpty() || $items->contains(fn ($item) => $item->kind !== OrderItemKind::MuaKhoa)) {
                throw new DomainRejection('Dòng đơn không đủ điều kiện cấp ghi danh.');
            }

            // CR-120: khoản muộn chỉ được chấp nhận khi lấy lại được chỗ của mã giảm giá.
            if (($slotReason = CouponSlots::retake($order)) !== null) {
                throw new DomainRejection($slotReason.' Không chấp nhận được khoản muộn; hãy hoàn khoản.');
            }
            $payment->update([
                'reconcile_status' => PaymentReconcileStatus::HopLe,
                'accepted_late_at' => now(),
                'accepted_late_by_id' => $actor->id,
            ]);
            $order->update(['status' => OrderStatus::DangCapQuyen, 'status_before_cxl' => null]);
            Audit::log('PAYMENT_ACCEPT_LATE', $actor, $payment,
                ['reconcile_status' => 'CAN_HOAN', 'order_status' => 'CAN_XU_LY'],
                ['reconcile_status' => 'HOP_LE', 'order_status' => 'DANG_CAP_QUYEN']);
            app(EnsureGrantJob::class)->locked($order);

            return $payment;
        });
    }
}
