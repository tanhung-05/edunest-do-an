<?php

namespace App\Http\Controllers\Gateway;

use App\Actions\M07\ReconcilePayment;
use App\Enums\M07\PaymentChannel;
use App\Enums\M07\PaymentEventOutcome;
use App\Http\Controllers\Controller;
use App\Models\M07\Payment;
use App\Support\DomainRejection;
use App\Support\M07\MoMoGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** P10 (CR-260): kết quả MoMo bản thử — trang quay lại (trình duyệt học viên) và IPN (máy chủ MoMo gọi). */
final class MoMoController extends Controller
{
    public function back(Request $request, ReconcilePayment $reconcile): RedirectResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->query();
        $payment = Payment::query()->where('transaction_code', (string) ($data['orderId'] ?? ''))->first();
        if ($payment === null) {
            return to_route('student.orders.index')->with('status', 'Không tìm thấy giao dịch MoMo.');
        }
        $message = $this->record($data, $reconcile);

        return to_route('student.orders.show', $payment->order)->with('status', $message);
    }

    public function ipn(Request $request, ReconcilePayment $reconcile): Response
    {
        /** @var array<string, mixed> $data */
        $data = $request->json()->all();
        $this->record($data, $reconcile);

        // MoMo chỉ cần 204 để ngừng gửi lại.
        return response()->noContent();
    }

    /** @param array<string, mixed> $data */
    private function record(array $data, ReconcilePayment $reconcile): string
    {
        $valid = MoMoGateway::verify($data);
        try {
            $event = $reconcile->handle(MoMoGateway::confirmation($data), is_string($data['signature'] ?? null) ? $data['signature'] : null, PaymentChannel::MoMo, $valid);
        } catch (DomainRejection $e) {
            return $e->getMessage();
        }

        return match (true) {
            $event->outcome === PaymentEventOutcome::TuChoiChuKy => 'Kết quả MoMo không hợp lệ (sai chữ ký), đơn chưa thay đổi.',
            (string) ($data['resultCode'] ?? '') === '0' => 'MoMo báo thanh toán thành công. Hệ thống đang mở khóa học cho bạn.',
            default => 'MoMo báo giao dịch chưa thành công'.(is_string($data['message'] ?? null) ? ': '.$data['message'] : '').'. Bạn có thể thanh toán lại.',
        };
    }
}
