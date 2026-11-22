<?php

namespace App\Support\M12;

use App\Enums\M07\OrderStatus;
use App\Enums\M12\CouponSlotStatus;
use App\Models\M07\Order;
use App\Models\M12\Coupon;
use App\Models\M12\CouponRedemption;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vòng đời chỗ/lượt mã giảm giá (CR-120, 02b MG-1..MG-4).
 * Chỗ đang chiếm = DA_DUNG + GIU_CHO của đơn còn CHO_THANH_TOAN và chưa quá hạn đơn,
 * nên chỗ của đơn quá hạn tự giải phóng trước tác vụ quét.
 */
final class CouponSlots
{
    /** @return Builder<CouponRedemption> */
    public static function occupied(Coupon $coupon, ?int $userId = null, ?int $exceptOrderId = null): Builder
    {
        $now = now();

        return CouponRedemption::query()->where('coupon_id', $coupon->id)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->when($exceptOrderId !== null, fn ($q) => $q->where('order_id', '<>', $exceptOrderId))
            ->where(fn ($q) => $q->where('status', CouponSlotStatus::DaDung)
                ->orWhere(fn ($q) => $q->where('status', CouponSlotStatus::GiuCho)
                    ->where('order_expires_at', '>=', $now)
                    ->whereIn('order_id', Order::query()->select('id')->where('status', OrderStatus::ChoThanhToan))));
    }

    /** Lý do không còn chỗ (null = còn). Gọi dưới khóa mã. */
    public static function unavailable(Coupon $coupon, int $userId, ?int $exceptOrderId = null): ?string
    {
        if (self::occupied($coupon, null, $exceptOrderId)->count() >= $coupon->total_limit) {
            return 'Mã giảm giá đã hết lượt.';
        }
        if (self::occupied($coupon, $userId, $exceptOrderId)->count() >= $coupon->per_user_limit) {
            return 'Bạn đã dùng hết lượt của mã giảm giá này.';
        }

        return null;
    }

    /** MG-2: đổi chỗ thành một lượt trong cùng lần ghi khoản HOP_LE. Đơn đã khóa. */
    public static function consume(Order $order): void
    {
        if ($order->coupon_id === null) {
            return;
        }
        CouponRedemption::query()->where('order_id', $order->id)->where('status', CouponSlotStatus::GiuCho)
            ->update(['status' => CouponSlotStatus::DaDung, 'used_at' => now(), 'updated_at' => now()]);
    }

    /** MG-3: giải phóng chỗ đúng một lần khi đơn hủy hoặc hết hạn. Đơn đã khóa. */
    public static function release(Order $order): void
    {
        if ($order->coupon_id === null) {
            return;
        }
        CouponRedemption::query()->where('order_id', $order->id)->where('status', CouponSlotStatus::GiuCho)
            ->update(['status' => CouponSlotStatus::DaTra, 'released_at' => now(), 'updated_at' => now()]);
    }

    /** MG-4: hoàn khoản từng HOP_LE trả lượt (CR-58). Đơn đã khóa. */
    public static function giveBack(Order $order): void
    {
        if ($order->coupon_id === null) {
            return;
        }
        CouponRedemption::query()->where('order_id', $order->id)->where('status', CouponSlotStatus::DaDung)
            ->update(['status' => CouponSlotStatus::DaTra, 'released_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Khoản muộn được chấp nhận phải lấy lại chỗ (CR-120). Trả lý do từ chối hoặc null. Thứ tự khóa orders → users → coupons.
     */
    public static function retake(Order $order): ?string
    {
        if ($order->coupon_id === null) {
            return null;
        }
        $coupon = Coupon::query()->whereKey($order->coupon_id)->lockForUpdate()->firstOrFail();
        $slot = CouponRedemption::query()->where('order_id', $order->id)->where('coupon_id', $coupon->id)->first();
        if ($slot !== null && $slot->status === CouponSlotStatus::DaDung) {
            return null;
        }
        if (($reason = self::unavailable($coupon, $order->user_id, $order->id)) !== null) {
            return $reason;
        }
        $slot?->update(['status' => CouponSlotStatus::DaDung, 'used_at' => now(), 'released_at' => null]);

        return null;
    }
}
