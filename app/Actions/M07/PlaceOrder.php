<?php

namespace App\Actions\M07;

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
use App\Models\User;
use App\Queries\M07\OpenOrders;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M12\Commerce;
use App\Support\TestBarrier;
use App\Support\Tx;
use Illuminate\Database\QueryException;

final class PlaceOrder
{
    public function handle(User $actor, Course $course, ?string $ref = null): Order|Enrollment
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $result = Tx::run(fn () => $this->place($actor, $course->id, $ref));
            } catch (QueryException $e) {
                if ($attempt >= 3 || Tx::code($e) !== 1062 || ! str_contains($e->getMessage(), 'orders_public_code_unique')) {
                    throw $e;
                }

                continue;
            }
            if (! is_int($result)) {
                return $result;
            }

            // DH-4 lấy khóa đơn trước, sau khi đã nhả gác đặt mua.
            app(ExpireOrder::class)->handle($result);
        }
    }

    private function place(User $actor, int $courseId, ?string $ref = null): Order|Enrollment|int
    {
        $student = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        OrderLocks::student($student);
        if (! $student->hasVerifiedEmail()) {
            throw DomainRejection::forbidden('Hãy xác minh email trước khi mua khóa.');
        }
        $course = Course::query()->whereKey($courseId)->sharedLock()->firstOrFail();
        TestBarrier::reach('place-order-locked');

        $enrollment = Enrollment::query()->where('user_id', $student->id)->where('course_id', $courseId)->first();
        if ($enrollment !== null) {
            return $enrollment;
        }
        $now = now();
        $open = OpenOrders::forCourses($student->id, [$courseId])->orderBy('id')->first();
        if ($open !== null) {
            return $open->status === OrderStatus::ChoThanhToan && $now->gt($open->expires_at) ? $open->id : $open;
        }
        if ($course->current_version_id === null || $course->sale_status === SaleStatus::ChuaBan) {
            throw new DomainRejection('Không tìm thấy khóa học.', 'general', 404);
        }
        if ($course->sale_status !== SaleStatus::DangBan) {
            throw new DomainRejection('Khóa học đã ngừng bán.');
        }

        [$price, $promotion] = Commerce::effectivePrice($course, $now);
        $order = new Order;
        $order->forceFill([
            'public_code' => self::publicCode(),
            'user_id' => $student->id,
            'status' => OrderStatus::ChoThanhToan,
            'source' => OrderSource::MuaNgay,
            'subtotal' => $price,
            'total' => $price,
            // QT-26 bước 15a (CR-164): tối đa một nguồn giới thiệu hợp lệ, chụp cùng lần tạo đơn.
            'affiliate_id' => Affiliates::sourceFor($ref, $student->id),
            'expires_at' => $now->addMinutes(Setting::number('order.payment_timeout_minutes')),
            'created_at' => $now,
        ])->save();

        $order->items()->create([
            'course_id' => $courseId,
            'kind' => OrderItemKind::MuaKhoa,
            'status' => OrderItemStatus::ChuaCap,
            'course_title' => $course->title,
            'list_price' => $course->price,
            'promotion_id' => $promotion?->id,
            'effective_price' => $price,
            'final_amount' => $price,
            'access_months' => $course->access_months,
        ]);

        return $order;
    }

    public static function publicCode(): string
    {
        // ADR-004: bỏ các ký tự dễ nhầm khi đọc mã đơn.
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = 'EDN-';
        for ($i = 0; $i < 10; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
