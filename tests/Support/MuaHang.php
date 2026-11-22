<?php

namespace Tests\Support;

use App\Actions\M05\CreateDraftVersion;
use App\Actions\M07\PlaceOrder;
use App\Actions\M07\ReconcilePayment;
use App\Actions\M07\StartPayment;
use App\Enums\M01\RoleCode;
use App\Enums\M07\PaymentEventResult;
use App\Enums\M08\EnrollmentSource;
use App\Enums\M08\LearningStatus;
use App\Models\M03\Course;
use App\Models\M05\CourseVersion;
use App\Models\M07\Order;
use App\Models\M07\OrderItem;
use App\Models\M07\Payment;
use App\Models\M07\PaymentEvent;
use App\Models\M08\Enrollment;
use App\Models\M11\Setting;
use App\Models\User;
use App\Support\M07\PaymentConfirmation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

final class MuaHang
{
    public const START = '2026-11-18 10:00:00';

    /** @return array<string, mixed> */
    public static function d7(): array
    {
        $fixture = CongKhai::d6();
        $fixture['a'] = taiKhoan('a@edunest.test', RoleCode::HocVien);
        $fixture['b'] = taiKhoan('b@edunest.test', RoleCode::HocVien);

        return $fixture;
    }

    public static function congBoMoi(Course $course, User $author, User $reviewer, User $publisher): CourseVersion
    {
        $version = app(CreateDraftVersion::class)->handle($author, $course)['version'];
        KhoaHoc::guiDuyet($version, $author);
        KhoaHoc::duyet($version, $reviewer);

        return KhoaHoc::congBo($version, $publisher);
    }

    public static function dat(User $user, Course $course): Order
    {
        $order = app(PlaceOrder::class)->handle($user, $course);
        expect($order)->toBeInstanceOf(Order::class);

        return $order;
    }

    public static function khoiTao(User $user, Order $order): Payment
    {
        return app(StartPayment::class)->handle($user, $order, true);
    }

    public static function payload(Payment|string $payment, ?int $amount = null, PaymentEventResult $result = PaymentEventResult::ThanhCong): string
    {
        return json_encode([
            'transaction_code' => $payment instanceof Payment ? $payment->transaction_code : $payment,
            'result' => $result->value,
            'received_amount' => $amount ?? ($payment instanceof Payment ? $payment->requested_amount : 499000),
        ], JSON_THROW_ON_ERROR);
    }

    public static function xacNhan(Payment $payment, ?int $amount = null, PaymentEventResult $result = PaymentEventResult::ThanhCong): PaymentEvent
    {
        $payload = self::payload($payment, $amount, $result);

        return app(ReconcilePayment::class)->handle($payload, PaymentConfirmation::sign($payload));
    }

    public static function chayCap(): bool
    {
        $job = Queue::connection('database')->pop('grants');
        if ($job === null) {
            return false;
        }

        $job->fire();

        return true;
    }

    public static function soViec(string $queue = 'grants'): int
    {
        return DB::table('jobs')->where('queue', $queue)->count();
    }

    public static function dong(Order $order): OrderItem
    {
        return $order->items()->sole();
    }

    public static function ghiDanh(User $user, Course $course, array $attributes = []): Enrollment
    {
        return Enrollment::query()->create($attributes + [
            'user_id' => $user->id, 'course_id' => $course->id, 'course_version_id' => $course->current_version_id,
            'learning_status' => LearningStatus::DangHoc, 'granted_at' => now(), 'source' => EnrollmentSource::DonHang,
        ]);
    }

    public static function giaLapLoi(bool $enabled): void
    {
        config(['app.demo' => true]);
        Setting::query()->updateOrCreate(['key' => 'grant.fault_mode'], ['value' => $enabled]);
    }

    public static function luc(string $time): void
    {
        test()->travelTo(Carbon::parse(strlen($time) === 8 ? '2026-11-18 '.$time : $time, 'Asia/Ho_Chi_Minh'));
    }
}
