<?php

namespace App\Queries\M07;

use App\Enums\M07\InvoicePdfStatus;
use App\Enums\M07\OrderItemKind;
use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentChannel;
use App\Enums\M07\PaymentReconcileStatus;
use App\Models\M07\Invoice;
use App\Models\M07\Order;
use App\Models\M07\OrderItem;
use App\Models\M07\Payment;
use App\Models\M08\Enrollment;
use App\Models\M11\AuditLog;
use App\Models\M12\Coupon;
use App\Support\Audit;

final class OrderView
{
    /** @return array<string, mixed> */
    public static function row(Order $order, bool $academic = false): array
    {
        return [
            'code' => $order->public_code,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'total' => $order->total,
            'created_at' => $order->created_at?->toISOString(),
            'expires_at' => $order->expires_at->toISOString(),
            'courses' => $order->items->pluck('course_title')->values()->all(),
            'student' => $academic ? $order->user->name : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Order $order, bool $academic = false): array
    {
        $order->load(['items', 'payments']);
        $hasValid = $order->payments->contains('reconcile_status', PaymentReconcileStatus::HopLe);
        $canRegrant = $hasValid && $order->grant_stopped_at === null
            && in_array($order->status, [OrderStatus::DangCapQuyen, OrderStatus::CanXuLy], true);
        $canAccept = $academic && ! $hasValid && $order->grant_stopped_at === null && $order->status === OrderStatus::CanXuLy
            && $order->status_before_cxl !== null && ! OpenOrders::forCourses($order->user_id, array_values($order->items->map(fn (OrderItem $item) => $item->course_id)->all()))
                ->whereKeyNot($order->id)->exists()
            && ! Enrollment::query()->where('user_id', $order->user_id)->whereIn('course_id', $order->items->pluck('course_id'))->exists();

        return self::row($order, $academic) + [
            'source' => $order->source->label(),
            'completed_at' => $order->completed_at?->toISOString(),
            'can_pay' => $order->status === OrderStatus::ChoThanhToan && $order->user->hasVerifiedEmail(),
            'can_cancel' => $order->status === OrderStatus::ChoThanhToan,
            // P5 — QT-13 b11–12 (CR-123): áp mã khi đơn chờ thanh toán chưa có mã và chưa có khoản nào.
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'coupon' => $order->coupon_id === null ? null : Coupon::query()->whereKey($order->coupon_id)->value('code'),
            'coupon_url' => ! $academic && $order->status === OrderStatus::ChoThanhToan && $order->coupon_id === null
                && $order->payments->isEmpty() && $order->items->contains('kind', OrderItemKind::MuaKhoa)
                ? route('student.orders.coupon', $order) : null,
            'invoice' => self::invoice($order, $academic),
            'stop_refund' => $academic ? self::stopRefund($order) : null,
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id, 'title' => $item->course_title, 'amount' => $item->final_amount,
                'kind' => $item->kind->value, 'kind_label' => $item->kind->label(),
                'list_price' => $item->list_price, 'effective_price' => $item->effective_price, 'discount' => $item->allocated_discount,
                'access_months' => $item->access_months, 'status' => $item->status->value, 'status_label' => $item->status->label(),
                'expiry_after' => $item->expiry_after?->toISOString(),
                'error' => $academic ? $item->grant_last_error : null,
                'can_regrant' => $academic && $canRegrant && $item->status === OrderItemStatus::CapLoi,
            ])->all(),
            'payments' => $order->payments->map(fn (Payment $payment) => self::payment($payment, $order, $academic && $canAccept))->all(),
        ];
    }

    /** @return array{number: string, status: string, url: ?string}|null */
    private static function invoice(Order $order, bool $academic): ?array
    {
        $invoice = Invoice::query()->where('order_id', $order->id)->first();
        if ($invoice === null) {
            return null;
        }

        return [
            'number' => $invoice->invoice_number, 'status' => $invoice->pdf_status->label(),
            'url' => ! $academic && $invoice->pdf_status === InvoicePdfStatus::SanSang ? route('student.orders.invoice', $order) : null,
        ];
    }

    /** CR-133/182: đơn cần xử lý, không còn dòng chờ cấp, 0 dòng đã cấp, còn khoản hợp lệ, chưa chọn hoàn. */
    private static function stopRefund(Order $order): ?string
    {
        $valid = $order->payments->first(fn (Payment $p) => $p->reconcile_status === PaymentReconcileStatus::HopLe);
        if ($valid === null || $order->status !== OrderStatus::CanXuLy || $order->grant_stopped_at !== null
            || $order->items->contains('status', OrderItemStatus::ChuaCap) || $order->items->contains('status', OrderItemStatus::DaCap)) {
            return null;
        }

        return route('academic.orders.payments.stop-grant-refund', [$order, $valid]);
    }

    /** @return array<string, mixed> */
    public static function payment(Payment $payment, Order $order, bool $canAccept = false): array
    {
        $needsRefund = $payment->reconcile_status === PaymentReconcileStatus::CanHoan;
        $reason = match (true) {
            $payment->is_late => 'Thu muộn',
            // AC-07-69: đã có khoản hợp lệ khác thì là thu trùng (E11), kể cả khi lệch tiền.
            $order->payments->contains(fn (Payment $p) => $p->id !== $payment->id && $p->reconcile_status === PaymentReconcileStatus::HopLe) => 'Thu trùng',
            $payment->received_amount !== $order->total => 'Sai số tiền',
            default => 'Thu trùng',
        };

        return [
            'id' => $payment->id,
            'code' => $payment->transaction_code,
            'requested_amount' => $payment->requested_amount,
            'received_amount' => $payment->received_amount,
            'channel' => $payment->channel->label(),
            'txn_status' => $payment->txn_status->value,
            'txn_status_label' => $payment->txn_status->label(),
            'reconcile_status' => $payment->reconcile_status?->value,
            'reconcile_status_label' => $payment->reconcile_status?->label(),
            'reason' => $needsRefund ? $reason : null,
            'refunded_at' => $payment->refunded_at?->toISOString(),
            'can_accept' => $canAccept && $needsRefund && $payment->is_late && $payment->received_amount === $order->total,
            'can_refund' => $needsRefund,
            'order_code' => $order->public_code,
            'order_status' => $order->status->label(),
            'simulated' => $payment->channel === PaymentChannel::MoPhong,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function audit(Order $order): array
    {
        $items = $order->items()->pluck('id');
        $payments = $order->payments()->pluck('id');

        return array_values(AuditLog::query()->where(fn ($q) => $q
            ->where(fn ($o) => $o->where('subject_type', Audit::alias($order))->where('subject_id', $order->id))
            ->orWhere(fn ($i) => $i->where('subject_type', 'order_item')->whereIn('subject_id', $items))
            ->orWhere(fn ($p) => $p->where('subject_type', 'payment')->whereIn('subject_id', $payments)))
            ->latest('id')->limit(100)->get()->map(fn (AuditLog $log) => [
                'id' => $log->id, 'action' => $log->action, 'actor_id' => $log->actor_id, 'at' => $log->created_at->toISOString(),
                'old' => $log->old_values, 'new' => $log->new_values, 'reason' => $log->reason,
            ])->all());
    }
}
