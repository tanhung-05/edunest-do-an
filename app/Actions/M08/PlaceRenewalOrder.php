<?php

namespace App\Actions\M08;

use App\Actions\M07\PlaceOrder;
use App\Enums\M07\OrderItemKind;
use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderSource;
use App\Enums\M07\OrderStatus;
use App\Models\M03\Course;
use App\Models\M07\Order;
use App\Models\M08\Enrollment;
use App\Models\M11\Setting;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\Tx;
use Illuminate\Database\QueryException;

/**
 * QT-15 b7–9, A4, A11 (NH-05). Đơn một dòng `GIA_HAN`, chụp giá gia hạn và số tháng; không áp mã,
 * không chống mua trùng; khóa `NGUNG_BAN` vẫn gia hạn được.
 */
final class PlaceRenewalOrder
{
    public function handle(User $actor, Enrollment $enrollment): Order
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return Tx::run(fn () => $this->place($actor, $enrollment->id));
            } catch (QueryException $e) {
                if ($attempt >= 3 || Tx::code($e) !== 1062 || ! str_contains($e->getMessage(), 'orders_public_code_unique')) {
                    throw $e;
                }
            }
        }
    }

    private function place(User $actor, int $enrollmentId): Order
    {
        $student = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        OrderLocks::student($student);
        if (! $student->hasVerifiedEmail()) {
            throw DomainRejection::forbidden('Hãy xác minh email trước khi gia hạn.');
        }
        $enrollment = Enrollment::query()->whereKey($enrollmentId)->firstOrFail();
        if ($enrollment->user_id !== $student->id) {
            throw DomainRejection::forbidden('Ghi danh không thuộc tài khoản của bạn.');
        }
        $course = Course::query()->whereKey($enrollment->course_id)->sharedLock()->firstOrFail();
        if ($enrollment->revoked_at !== null) {
            throw new DomainRejection('Quyền học đã bị thu hồi. Hãy liên hệ giáo vụ.');
        }
        if ($enrollment->het_han_luc === null) {
            throw new DomainRejection('Ghi danh vĩnh viễn không cần gia hạn.');
        }
        if ($course->extension_price === null || $course->access_months === null) {
            throw new DomainRejection('Khóa học chưa mở bán gia hạn.');
        }

        $now = now();
        $order = new Order;
        $order->forceFill([
            'public_code' => PlaceOrder::publicCode(), 'user_id' => $student->id, 'status' => OrderStatus::ChoThanhToan,
            'source' => OrderSource::GiaHan, 'subtotal' => $course->extension_price, 'total' => $course->extension_price,
            'expires_at' => $now->addMinutes(Setting::number('order.payment_timeout_minutes')), 'created_at' => $now,
        ])->save();
        $order->items()->create([
            'course_id' => $course->id, 'kind' => OrderItemKind::GiaHan, 'status' => OrderItemStatus::ChuaCap,
            'course_title' => $course->title, 'list_price' => $course->extension_price,
            'effective_price' => $course->extension_price, 'final_amount' => $course->extension_price,
            'access_months' => $course->access_months, 'enrollment_id' => $enrollment->id,
        ]);

        return $order;
    }
}
