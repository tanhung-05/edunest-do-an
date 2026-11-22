<?php

namespace Database\Seeders;

use App\Actions\M08\GrantOrderItem;
use App\Enums\M03\SaleStatus;
use App\Enums\M07\OrderItemKind;
use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderSource;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentChannel;
use App\Enums\M07\PaymentEventOutcome;
use App\Enums\M07\PaymentEventResult;
use App\Enums\M07\PaymentReconcileStatus;
use App\Enums\M07\PaymentTxnStatus;
use App\Models\M03\Course;
use App\Models\M07\Order;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Support\M07\PaymentConfirmation;
use App\Support\Tx;
use Illuminate\Database\Seeder;

final class OrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()->where('email', 'an.nguyen@edunest.test')->firstOrFail();
        $courses = Course::query()->where('sale_status', SaleStatus::DangBan)
            ->whereNotNull('current_version_id')->orderBy('id')->limit(5)->get()->values();
        if ($courses->count() < 5) {
            return;
        }

        foreach (['XONG', 'HETHAN', 'SAITIEN', 'CAPLOI', 'CANHOAN'] as $i => $scenario) {
            $course = $courses[$i];
            Tx::run(fn () => $this->seedOrder($student, $course, $scenario));
        }
    }

    private function seedOrder(User $student, Course $course, string $scenario): void
    {
        $code = 'EDN-'.match ($scenario) {
            'XONG' => 'K7Q2M9TXWA',
            'HETHAN' => 'H4VN8RZKPC',
            'SAITIEN' => 'S9TB3WQEMY',
            'CAPLOI' => 'C2FR6KXVNH',
            default => 'R8YD4PMGTQ',
        };
        // Cùng gác với đặt mua; từng mã demo chỉ khởi tạo một lần.
        User::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
        if (Order::query()->where('public_code', $code)->exists()
            || Enrollment::query()->where('user_id', $student->id)->where('course_id', $course->id)->exists()) {
            return;
        }

        $now = now();
        $status = match ($scenario) {
            'XONG' => OrderStatus::HoanTat,
            'HETHAN' => OrderStatus::HetHan,
            'SAITIEN' => OrderStatus::ChoThanhToan,
            default => OrderStatus::CanXuLy,
        };

        $order = new Order;
        $order->forceFill([
            'public_code' => $code, 'user_id' => $student->id, 'source' => OrderSource::MuaNgay,
            'status' => $status, 'subtotal' => $course->price, 'total' => $course->price,
            'created_at' => $scenario === 'SAITIEN' ? $now : $now->subHours(2),
            'expires_at' => $scenario === 'SAITIEN' ? $now->addMinutes(30) : $now->subMinutes(90),
            'expired_at' => in_array($scenario, ['HETHAN', 'CANHOAN'], true) ? $now->subMinutes(89) : null,
            'status_before_cxl' => $scenario === 'CANHOAN' ? OrderStatus::HetHan : null,
            'completed_at' => $scenario === 'XONG' ? $now : null,
            'first_completed_at' => $scenario === 'XONG' ? $now : null,
        ])->save();
        $item = $order->items()->create([
            'course_id' => $course->id, 'course_title' => $course->title, 'kind' => OrderItemKind::MuaKhoa,
            'status' => $scenario === 'CAPLOI' ? OrderItemStatus::CapLoi : OrderItemStatus::ChuaCap,
            'list_price' => $course->price, 'effective_price' => $course->price, 'final_amount' => $course->price,
            'access_months' => $course->access_months, 'grant_attempts' => $scenario === 'CAPLOI' ? 4 : 0,
            'grant_last_error' => $scenario === 'CAPLOI' ? 'Lỗi cấp quyền mô phỏng.' : null,
        ]);

        if ($scenario === 'XONG') {
            app(GrantOrderItem::class)->locked($order, $item);
        }
        if ($scenario === 'HETHAN') {
            return;
        }

        $amount = $scenario === 'SAITIEN' ? max(0, $course->price - 1000) : $course->price;
        $payment = $order->payments()->create([
            'transaction_code' => 'GD-DEMO-'.$scenario, 'channel' => PaymentChannel::MoPhong, 'txn_status' => PaymentTxnStatus::ThanhCong,
            'requested_amount' => $course->price, 'received_amount' => $amount,
            'reconcile_status' => in_array($scenario, ['SAITIEN', 'CANHOAN'], true) ? PaymentReconcileStatus::CanHoan : PaymentReconcileStatus::HopLe,
            'is_late' => $scenario === 'CANHOAN', 'terms_accepted_at' => $now, 'succeeded_at' => $now, 'reconciled_at' => $now,
        ]);
        $payload = json_encode([
            'transaction_code' => $payment->transaction_code,
            'result' => PaymentEventResult::ThanhCong->value,
            'received_amount' => $amount,
        ], JSON_THROW_ON_ERROR);
        $payment->events()->create([
            'transaction_code' => $payment->transaction_code, 'result' => PaymentEventResult::ThanhCong, 'signature_valid' => true,
            'payload' => $payload, 'signature' => PaymentConfirmation::sign($payload), 'received_at' => $now, 'outcome' => PaymentEventOutcome::DaGhi,
        ]);
    }
}
