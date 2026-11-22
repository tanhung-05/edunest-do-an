<?php

namespace App\Actions\M08;

use App\Enums\M05\CourseVersionStatus;
use App\Enums\M07\OrderItemKind;
use App\Enums\M07\OrderItemStatus;
use App\Enums\M08\EnrollmentSource;
use App\Enums\M08\LearningStatus;
use App\Events\M08\EnrollmentGranted;
use App\Models\M07\Order;
use App\Models\M07\OrderItem;
use App\Models\M08\Enrollment;
use App\Support\Audit;
use App\Support\M03\CourseLocks;
use App\Support\M07\GrantRun;
use App\Support\M07\OrderLocks;
use App\Support\Tx;
use Closure;

final class GrantOrderItem
{
    public function handle(GrantRun $run): bool
    {
        return Tx::run(function () use ($run) {
            $order = OrderLocks::order($run->orderId);
            OrderLocks::users($order);
            $item = $run->current($order);
            if ($item === null) {
                return false;
            }

            return $this->locked($order, $item, fn () => $run->current($order) !== null);
        });
    }

    /**
     * Chia sẻ GH-1/HL-1 giữa cấp tự động và cấp lại. Đơn và người dùng đã khóa.
     *
     * @param  (Closure(): bool)|null  $stillCurrent
     */
    public function locked(Order $order, OrderItem $item, ?Closure $stillCurrent = null): bool
    {
        $course = CourseLocks::course($item->course_id);
        $version = $course->currentVersion()->where('status', CourseVersionStatus::DaCongBo)->first();
        $existing = Enrollment::query()->where('user_id', $order->user_id)->where('course_id', $course->id)->lockForUpdate()->first();
        if ($stillCurrent !== null && ! $stillCurrent()) {
            return false;
        }

        // Hết quyền chạy. Giữ mã để bước phục hồi nhận đúng kết quả đã ghi.
        $clear = ['grant_run_expires_at' => null, 'grant_next_attempt_at' => null];
        if ($item->kind === OrderItemKind::GiaHan) {
            return $this->extend($order, $item, $existing, $clear);
        }
        $error = match (true) {
            $version === null => 'Khóa chưa có bản công bố.',
            $existing !== null => 'Học viên đã có ghi danh cho khóa.',
            default => null,
        };
        if ($error !== null) {
            $item->update($clear + ['status' => OrderItemStatus::CapLoi, 'grant_last_error' => $error]);
            Audit::log('ORDER_ITEM_GRANT_FAILED', null, $item, ['status' => 'CHUA_CAP'], ['status' => 'CAP_LOI'], $error);

            return true;
        }

        $now = now();
        $end = $item->access_months === null ? null : $now->addMonthsNoOverflow($item->access_months);
        $enrollment = Enrollment::query()->create([
            'user_id' => $order->user_id,
            'course_id' => $course->id,
            'course_version_id' => $version->id,
            'learning_status' => LearningStatus::DangHoc,
            'source' => EnrollmentSource::DonHang,
            'order_item_id' => $item->id,
            'granted_at' => $now,
            'het_han_luc' => $end,
        ]);
        $item->update($clear + [
            'status' => OrderItemStatus::DaCap, 'enrollment_id' => $enrollment->id, 'granted_at' => $now,
            'expiry_after' => $end, 'grant_last_error' => null,
        ]);
        EnrollmentGranted::dispatch($enrollment);

        return true;
    }

    /**
     * QT-15 b11, A5, A6, A10 (HL-3/HL-4, E17): cộng hạn đúng một lần dưới khóa ghi danh; `DA_THU_HOI` → `CAP_LOI`.
     *
     * @param  array<string, null>  $clear
     */
    private function extend(Order $order, OrderItem $item, ?Enrollment $enrollment, array $clear): bool
    {
        $error = match (true) {
            $enrollment === null => 'Học viên chưa có ghi danh để gia hạn.',
            $enrollment->revoked_at !== null => 'Ghi danh đã bị thu hồi, không cộng hạn.',
            $enrollment->het_han_luc === null => 'Ghi danh vĩnh viễn, không gia hạn.',
            $item->access_months === null => 'Dòng gia hạn thiếu số tháng.',
            default => null,
        };
        if ($error !== null) {
            $item->update($clear + ['status' => OrderItemStatus::CapLoi, 'grant_last_error' => $error]);
            Audit::log('ORDER_ITEM_GRANT_FAILED', null, $item, ['status' => 'CHUA_CAP'], ['status' => 'CAP_LOI'], $error);

            return true;
        }
        $now = now();
        $before = $enrollment->het_han_luc;
        $base = $now->lt($before) ? $before : $now;
        $after = $base->addMonthsNoOverflow($item->access_months);
        $enrollment->update(['het_han_luc' => $after]);
        $item->update($clear + [
            'status' => OrderItemStatus::DaCap, 'enrollment_id' => $enrollment->id, 'granted_at' => $now,
            'expiry_before' => $before, 'expiry_after' => $after, 'grant_last_error' => null,
        ]);
        Audit::log('ENROLLMENT_EXTEND', null, $enrollment, ['het_han_luc' => $before->toIso8601String()], ['het_han_luc' => $after->toIso8601String()]);

        return true;
    }
}
