<?php

namespace App\Actions\M12;

use App\Actions\M07\ExpireOrder;
use App\Enums\M07\OrderItemKind;
use App\Enums\M07\OrderStatus;
use App\Enums\M12\CouponScope;
use App\Enums\M12\CouponSlotStatus;
use App\Enums\M12\DiscountType;
use App\Models\M02\Category;
use App\Models\M03\Course;
use App\Models\M07\Order;
use App\Models\M07\OrderItem;
use App\Models\M12\Coupon;
use App\Models\M12\CouponRedemption;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M12\Commerce;
use App\Support\M12\CouponSlots;
use App\Support\TestBarrier;
use App\Support\Tx;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;

/**
 * QT-13 b11–12, A5, A6, A9, A10 (MG-1). Khóa `orders` → `users` → `coupons` (05 §1).
 * Chỉ áp trên đơn `CHO_THANH_TOAN` chưa có mã và chưa có khoản nào (CR-123).
 */
final class ApplyCoupon
{
    public function handle(User $actor, Order $order, string $rawCode): Order
    {
        $result = Tx::run(function () use ($actor, $order, $rawCode) {
            $order = OrderLocks::order($order->id);
            OrderLocks::student(OrderLocks::users($order, $actor), $order->user_id);
            if (app(ExpireOrder::class)->locked($order)) {
                return null;
            }
            if ($order->status !== OrderStatus::ChoThanhToan) {
                throw new DomainRejection('Đơn không còn chờ thanh toán.', 'code');
            }
            if ($order->coupon_id !== null) {
                throw new DomainRejection('Đơn đã áp một mã giảm giá; mỗi đơn chỉ áp một mã.', 'code');
            }
            if ($order->payments()->exists()) {
                throw new DomainRejection('Đơn đã có khoản thanh toán nên không áp mã được. Hãy hủy và đặt lại đơn nếu muốn dùng mã.', 'code');
            }
            $items = $order->items()->orderBy('sort_order')->orderBy('id')->get();
            if ($items->every(fn (OrderItem $i) => $i->kind === OrderItemKind::GiaHan)) {
                throw new DomainRejection('Mã giảm giá không áp cho đơn gia hạn.', 'code');
            }

            $code = Commerce::normalizeCode($rawCode);
            $coupon = $code === '' ? null : Coupon::query()->where('code', $code)->lockForUpdate()->first();
            TestBarrier::reach('apply-coupon-locked');
            if ($coupon === null) {
                throw new DomainRejection('Mã giảm giá không tồn tại.', 'code');
            }
            $now = now();
            if ($now->lt($coupon->starts_at)) {
                throw new DomainRejection('Mã giảm giá chưa tới hạn sử dụng.', 'code');
            }
            if ($now->gte($coupon->ends_at)) {
                throw new DomainRejection('Mã giảm giá đã hết hạn.', 'code');
            }
            if (($reason = CouponSlots::unavailable($coupon, $order->user_id, $order->id)) !== null) {
                throw new DomainRejection($reason, 'code');
            }

            $eligible = $items->filter(fn (OrderItem $i) => $i->kind === OrderItemKind::MuaKhoa && $this->inScope($coupon, $i->course_id));
            if ($eligible->isEmpty()) {
                throw new DomainRejection('Mã giảm giá không áp dụng cho khóa trong đơn.', 'code');
            }
            $base = (int) $eligible->sum('effective_price');
            if ($base < $coupon->min_order) {
                throw new DomainRejection('Đơn chưa đủ giá trị tối thiểu '.Commerce::vnd($coupon->min_order).' để dùng mã này.', 'code');
            }

            $discount = $coupon->discount_type === DiscountType::PhanTram
                ? min(BigInteger::of($base)->multipliedBy($coupon->value)->dividedBy(100, RoundingMode::Down)->toInt(), (int) $coupon->max_discount)
                : $coupon->value;
            $discount = min($discount, $base);
            $shares = Commerce::allocate($discount, $eligible->mapWithKeys(fn (OrderItem $i) => [$i->id => $i->effective_price])->all());
            foreach ($eligible as $item) {
                $share = $shares[$item->id] ?? 0;
                $item->update(['allocated_discount' => $share, 'final_amount' => $item->effective_price - $share]);
            }
            $order->update(['coupon_id' => $coupon->id, 'discount_total' => $discount, 'total' => $order->subtotal - $discount]);
            CouponRedemption::query()->create([
                'coupon_id' => $coupon->id, 'order_id' => $order->id, 'user_id' => $order->user_id,
                'status' => CouponSlotStatus::GiuCho, 'discount_amount' => $discount,
                'order_expires_at' => $order->expires_at, 'held_at' => $now,
            ]);

            return $order;
        });

        return $result ?? throw new DomainRejection('Đơn đã hết hạn.', 'code');
    }

    /** BR-TT-57, CR-125: danh mục cấp 1 gồm khóa ở danh mục con. */
    private function inScope(Coupon $coupon, int $courseId): bool
    {
        return match ($coupon->scope_type) {
            CouponScope::ToanBo => true,
            CouponScope::Khoa => $coupon->scope_course_id === $courseId,
            CouponScope::DanhMuc => ($categoryId = Course::query()->whereKey($courseId)->value('category_id')) !== null
                && ((int) $categoryId === $coupon->scope_category_id
                    || (int) Category::withTrashed()->whereKey($categoryId)->value('parent_id') === $coupon->scope_category_id),
        };
    }
}
