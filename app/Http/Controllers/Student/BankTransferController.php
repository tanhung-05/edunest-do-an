<?php

namespace App\Http\Controllers\Student;

use App\Actions\M28\BankTransfers;
use App\Http\Controllers\Controller;
use App\Models\M07\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** QT-30 bước 1–2: học viên đồng ý điều khoản và mở QR chuyển khoản của đơn; demo mô phỏng ngân hàng báo đã chuyển. */
final class BankTransferController extends Controller
{
    public function store(Request $request, Order $order, BankTransfers $transfers): RedirectResponse
    {
        Gate::authorize('pay', $order);
        $transfers->open($this->actor($request), $order, $request->boolean('terms'));

        return to_route('student.orders.show', $order)->with('status', 'Quét mã QR hoặc chuyển khoản đúng nội dung để thanh toán.');
    }

    /** Chỉ môi trường demo: bộ mô phỏng ngân hàng phát giao dịch và xác nhận đúng số tiền của đơn. */
    public function simulate(Request $request, Order $order, BankTransfers $transfers): RedirectResponse
    {
        Gate::authorize('pay', $order);
        abort_unless(config()->boolean('services.payment_sim.demo') || app()->environment(['local', 'testing']), 404);
        abort_if($order->transfer_terms_accepted_at === null, 403);
        $data = $request->validate(['amount' => ['nullable', 'integer', 'min:0']]);
        $result = $transfers->simulate($order, isset($data['amount']) ? (int) $data['amount'] : $order->total);

        $message = match ($result['outcome']) {
            'DA_GHI' => 'Ngân hàng mô phỏng đã báo nhận tiền. Đơn đang được cấp quyền học.',
            'TRUNG_LAP' => 'Giao dịch này đã được ghi trước đó.',
            default => 'Ngân hàng mô phỏng đã báo, giao dịch cần giáo vụ tra soát.',
        };

        return to_route('student.orders.show', $order)->with('status', $message);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
