<?php

namespace App\Actions\M24;

use App\Enums\M07\PaymentReconcileStatus;
use App\Enums\M24\CommissionStatus;
use App\Models\M07\Order;
use App\Models\M24\Affiliate;
use App\Models\M24\AffiliateCommission;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\Tx;
use Carbon\CarbonImmutable;

/**
 * QT-26 bước 16–18, A6, A9 (BR-TT-73/74, CR-95/165). Thứ tự khóa: đơn → hoa hồng.
 * Hoa hồng = tổng đơn sau giảm × tỷ lệ chụp ở lần hoàn tất đầu, làm tròn xuống đồng; hoàn khoản thì hủy.
 */
final class AffiliateCommissions
{
    /** Bước 16: gọi dưới khóa đơn ngay khi đơn lần đầu `HOAN_TAT`. Chạy lại không tạo thêm. */
    public static function onFirstCompletion(Order $order): void
    {
        if ($order->affiliate_id === null || $order->affiliate_commission_eligible !== null) {
            return;
        }
        $affiliate = Affiliate::query()->find($order->affiliate_id);
        $refunded = $order->payments()->where('reconcile_status', PaymentReconcileStatus::DaHoanLai)->exists();
        if ($affiliate === null || $refunded) {
            // CR-165: đã hoàn khoản trước lần hoàn tất đầu → 0 bản hoa hồng, ghi lý do vào lịch sử đơn.
            $order->forceFill(['affiliate_commission_eligible' => false])->save();
            Audit::log('affiliate.ineligible', null, $order, null, ['reason' => 'Đơn đã có khoản hoàn trước khi hoàn tất']);

            return;
        }
        $rate = $affiliate->rate_bp;
        $order->forceFill(['affiliate_commission_eligible' => true, 'affiliate_rate_bp_snapshot' => $rate])->save();
        AffiliateCommission::query()->firstOrCreate(['order_id' => $order->id], [
            'affiliate_id' => $affiliate->id, 'base_amount' => $order->total, 'rate_bp' => $rate,
            'amount' => intdiv($order->total * $rate, 10000), 'status' => CommissionStatus::ChoDuyet,
            'recorded_at' => CarbonImmutable::now(),
        ]);
    }

    /** A6: gọi dưới khóa đơn khi một khoản của đơn được ghi `DA_HOAN_LAI`. */
    public static function cancelForRefund(Order $order, string $reason): void
    {
        $commission = AffiliateCommission::query()->where('order_id', $order->id)->lockForUpdate()->first();
        if ($commission === null || $commission->status === CommissionStatus::Huy) {
            return;
        }
        $old = $commission->status->value;
        $commission->forceFill(['status' => CommissionStatus::Huy, 'cancelled_at' => CarbonImmutable::now(), 'cancel_reason' => mb_substr($reason, 0, 200)])->save();
        Audit::log('affiliate.commission.cancel', null, $commission, ['status' => $old], ['status' => 'HUY'], $reason);
    }

    /** Bước 17–18: Giáo vụ duyệt chi trả mô phỏng, đúng một lần; đơn có khoản đã hoàn thì hủy thay vì chi. */
    public function pay(User $actor, int $commissionId): AffiliateCommission
    {
        $result = Tx::run(function () use ($actor, $commissionId) {
            $orderId = AffiliateCommission::query()->whereKey($commissionId)->value('order_id');
            if ($orderId === null) {
                return new DomainRejection('Không tìm thấy hoa hồng.', 'general', 404);
            }
            $order = OrderLocks::order((int) $orderId);
            OrderLocks::academic(OrderLocks::users($order, $actor));
            $commission = AffiliateCommission::query()->whereKey($commissionId)->lockForUpdate()->firstOrFail();
            if ($commission->status !== CommissionStatus::ChoDuyet) {
                return new DomainRejection('Hoa hồng không còn ở trạng thái chờ duyệt.');
            }
            if ($order->payments()->where('reconcile_status', PaymentReconcileStatus::DaHoanLai)->exists()) {
                self::cancelForRefund($order, 'Đơn đã có khoản hoàn');

                return new DomainRejection('Đơn đã có khoản hoàn nên hoa hồng bị hủy.');
            }
            $commission->forceFill(['status' => CommissionStatus::DaChi, 'paid_at' => CarbonImmutable::now(), 'paid_by_id' => $actor->id])->save();
            Audit::log('affiliate.commission.pay', $actor, $commission, ['status' => 'CHO_DUYET'], ['status' => 'DA_CHI', 'amount' => $commission->amount]);

            return $commission;
        });
        if ($result instanceof DomainRejection) {
            throw $result;
        }

        return $result;
    }

    /** A9: quét đơn hoàn tất chưa xét hoa hồng và hoa hồng còn hiệu lực của đơn đã có khoản hoàn. */
    public function sweep(): int
    {
        $done = 0;
        $pending = Order::query()->whereNotNull('affiliate_id')->whereNotNull('first_completed_at')
            ->whereNull('affiliate_commission_eligible')->pluck('id');
        $refunded = AffiliateCommission::query()->where('status', '!=', CommissionStatus::Huy)
            ->whereHas('order.payments', fn ($q) => $q->where('reconcile_status', PaymentReconcileStatus::DaHoanLai))->pluck('order_id');
        foreach ($pending->merge($refunded)->unique() as $id) {
            Tx::run(function () use ($id) {
                $order = OrderLocks::order((int) $id);
                if ($order->first_completed_at !== null) {
                    self::onFirstCompletion($order);
                }
                if ($order->payments()->where('reconcile_status', PaymentReconcileStatus::DaHoanLai)->exists()) {
                    self::cancelForRefund($order, 'Đơn đã có khoản hoàn');
                }
            });
            $done++;
        }

        return $done;
    }
}
