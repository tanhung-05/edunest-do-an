<?php

namespace App\Actions\M12;

use App\Actions\M07\ExpireOrder;
use App\Actions\M07\PlaceOrder;
use App\Actions\M24\Affiliates;
use App\Enums\M03\SaleStatus;
use App\Enums\M07\OrderItemKind;
use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderSource;
use App\Enums\M07\OrderStatus;
use App\Models\M03\Course;
use App\Models\M07\Order;
use App\Models\M08\Enrollment;
use App\Models\M11\Setting;
use App\Models\M12\Cart;
use App\Models\M12\CartItem;
use App\Models\User;
use App\Queries\M07\OpenOrders;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M12\Commerce;
use App\Support\TestBarrier;
use App\Support\Tx;
use Illuminate\Database\QueryException;

/**
 * QT-13 b8–10, A4, A8, E10, E14 (CR-119, CR-126). Một giao dịch dưới gác `users` của học viên
 * (khóa chung học viên–khóa với Mua ngay, đổi mã và Job cấp), rồi khóa `courses` theo id tăng dần.
 */
final class PlaceCartOrder
{
    /** @return array{order: ?Order, blocked: list<Order>, excluded: list<string>} */
    public function handle(User $actor, ?string $ref = null): array
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $result = Tx::run(fn () => $this->place($actor, $ref));
            } catch (QueryException $e) {
                if ($attempt >= 3 || Tx::code($e) !== 1062 || ! str_contains($e->getMessage(), 'orders_public_code_unique')) {
                    throw $e;
                }

                continue;
            }
            if (is_int($result)) {
                // Đơn mở đã quá hạn: chốt DH-4 trong giao dịch riêng rồi thử lại (cùng cách PlaceOrder).
                app(ExpireOrder::class)->handle($result);

                continue;
            }

            return $result;
        }
    }

    /** @return array{order: ?Order, blocked: list<Order>, excluded: list<string>}|int */
    private function place(User $actor, ?string $ref = null): array|int
    {
        $student = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        OrderLocks::student($student);
        if (! $student->hasVerifiedEmail()) {
            throw DomainRejection::forbidden('Hãy xác minh email trước khi thanh toán giỏ.');
        }
        $cart = Cart::query()->where('user_id', $student->id)->first();
        $courseIds = $cart === null ? [] : array_values(array_map('intval', $cart->items()->orderBy('id')->pluck('course_id')->all()));
        if ($courseIds === []) {
            throw new DomainRejection('Giỏ hàng đang trống.', 'cart');
        }
        TestBarrier::reach('place-cart-order-locked');

        $now = now();
        $blocked = [];
        foreach (OpenOrders::forCourses($student->id, $courseIds, true)->orderBy('id')->get() as $open) {
            if ($open->status === OrderStatus::ChoThanhToan && $now->gt($open->expires_at)) {
                return $open->id;
            }
            $blocked[] = $open;
        }
        if ($blocked !== []) {
            return ['order' => null, 'blocked' => $blocked, 'excluded' => []];
        }

        $sorted = $courseIds;
        sort($sorted);
        $courses = Course::query()->whereKey($sorted)->orderBy('id')->sharedLock()->get()->keyBy('id');
        $owned = Enrollment::query()->where('user_id', $student->id)->whereIn('course_id', $courseIds)->pluck('course_id')->all();
        $eligible = [];
        $excluded = [];
        foreach ($courseIds as $id) {
            $course = $courses->get($id);
            if ($course === null) {
                continue;
            }
            if (in_array($id, $owned, true)) {
                $excluded[] = $course->title.': bạn đã sở hữu khóa này.';
            } elseif ($course->sale_status !== SaleStatus::DangBan || $course->current_version_id === null) {
                $excluded[] = $course->title.': khóa đã ngừng bán.';
            } else {
                $eligible[] = $course;
            }
        }
        if ($eligible === []) {
            throw new DomainRejection('Giỏ không còn khóa đủ điều kiện đặt mua. '.implode(' ', $excluded), 'cart');
        }

        $lines = [];
        $subtotal = 0;
        foreach ($eligible as $position => $course) {
            [$price, $promotion] = Commerce::effectivePrice($course, $now);
            $subtotal += $price;
            $lines[] = [
                'course_id' => $course->id, 'kind' => OrderItemKind::MuaKhoa, 'status' => OrderItemStatus::ChuaCap,
                'course_title' => $course->title, 'list_price' => $course->price, 'promotion_id' => $promotion?->id,
                'effective_price' => $price, 'final_amount' => $price, 'access_months' => $course->access_months,
                'sort_order' => $position,
            ];
        }
        $order = new Order;
        $order->forceFill([
            'public_code' => PlaceOrder::publicCode(), 'user_id' => $student->id, 'status' => OrderStatus::ChoThanhToan,
            'source' => OrderSource::GioHang, 'subtotal' => $subtotal, 'total' => $subtotal,
            'affiliate_id' => Affiliates::sourceFor($ref, $student->id),
            'expires_at' => $now->addMinutes(Setting::number('order.payment_timeout_minutes')), 'created_at' => $now,
        ])->save();
        $order->items()->createMany($lines);
        // CR-126: chỉ lấy đúng các khóa đã tạo dòng ra khỏi giỏ, trong cùng lần ghi.
        // Khóa đã sở hữu cũng rời giỏ (M12 "bỏ khóa đã sở hữu ra khỏi giỏ", AC-13-16).
        CartItem::query()->where('cart_id', $cart->id)
            ->whereIn('course_id', [...array_map(fn (Course $c) => $c->id, $eligible), ...$owned])->delete();

        return ['order' => $order, 'blocked' => [], 'excluded' => $excluded];
    }
}
