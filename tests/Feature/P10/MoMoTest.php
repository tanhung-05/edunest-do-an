<?php

use App\Enums\M07\PaymentChannel;
use App\Enums\M07\PaymentEventOutcome;
use App\Enums\M07\PaymentTxnStatus;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use App\Models\M07\PaymentEvent;
use App\Support\M07\MoMoGateway;
use Illuminate\Support\Facades\Http;
use Tests\Support\MuaHang as MH;

pest()->group('P10');

beforeEach(function () {
    $this->d = MH::d7();
    $this->a = $this->d['a'];
    MH::luc('10:00:00');
    // Giá trị giả cho test, không phải khóa MoMo.
    config(['services.momo' => ['endpoint' => 'https://test-payment.momo.vn', 'partner_code' => 'EDUTEST', 'access_key' => 'ak-test', 'secret_key' => 'sk-test']]);
});

afterEach(function () {
    $this->travelBack();
});

/** @return array<string, string> */
function momoKetQua(Payment $payment, int $resultCode, ?int $amount = null): array
{
    $data = [
        'partnerCode' => 'EDUTEST', 'orderId' => $payment->transaction_code, 'requestId' => $payment->transaction_code,
        'amount' => (string) ($amount ?? $payment->requested_amount), 'orderInfo' => 'Thanh toan', 'orderType' => 'momo_wallet',
        'transId' => '4088878653', 'resultCode' => (string) $resultCode, 'message' => $resultCode === 0 ? 'Thành công.' : 'Giao dịch bị từ chối.',
        'payType' => 'qr', 'responseTime' => '1763800000000', 'extraData' => '',
    ];
    $data['signature'] = MoMoGateway::sign(['accessKey' => 'ak-test'] + array_diff_key($data, ['signature' => 1]));

    return $data;
}

it('CR-260: chọn MoMo thì tạo giao dịch ở MoMo bản thử và chuyển sang trang thanh toán của MoMo', function () {
    Http::fake(['test-payment.momo.vn/*' => Http::response(['resultCode' => 0, 'message' => 'OK', 'payUrl' => 'https://test-payment.momo.vn/v2/gateway/pay?t=abc'])]);
    $order = MH::dat($this->a, $this->d['k1']);
    $this->signIn($this->a)->get(route('student.orders.show', $order))->assertInertia(fn ($p) => $p->where('momo_enabled', true));

    $this->post(route('student.orders.payments.store', $order), ['terms' => 1, 'gateway' => 'MOMO'])
        ->assertRedirect('https://test-payment.momo.vn/v2/gateway/pay?t=abc');
    $payment = $order->payments()->sole();
    expect($payment->channel)->toBe(PaymentChannel::MoMo);
    Http::assertSent(function ($request) use ($payment) {
        $body = $request->data();

        return $request->url() === 'https://test-payment.momo.vn/v2/gateway/api/create'
            && $body['orderId'] === $payment->transaction_code && $body['amount'] === (string) $payment->requested_amount
            && $body['requestType'] === 'captureWallet' && strlen($body['signature']) === 64 && ! isset($body['accessKey']);
    });
});

it('CR-260: kết quả MoMo đúng chữ ký thì đối soát như cổng mô phỏng; sai chữ ký thì bị chặn', function () {
    Http::fake(['test-payment.momo.vn/*' => Http::response(['resultCode' => 0, 'payUrl' => 'https://test-payment.momo.vn/pay'])]);
    $order = MH::dat($this->a, $this->d['k1']);
    $this->signIn($this->a)->post(route('student.orders.payments.store', $order), ['terms' => 1, 'gateway' => 'MOMO']);
    $payment = $order->payments()->sole();

    $forged = momoKetQua($payment, 0);
    $forged['signature'] = str_repeat('0', 64);
    $this->get(route('payments.momo.return', $forged))->assertRedirect(route('student.orders.show', $order));
    expect($payment->fresh()->txn_status)->toBe(PaymentTxnStatus::KhoiTao)
        ->and(PaymentEvent::query()->latest('id')->value('outcome'))->toBe(PaymentEventOutcome::TuChoiChuKy);

    $this->get(route('payments.momo.return', momoKetQua($payment, 0)))->assertRedirect(route('student.orders.show', $order))
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'thành công'));
    expect($payment->fresh()->txn_status)->toBe(PaymentTxnStatus::ThanhCong);

    // IPN gửi lại cùng kết quả: không đổi gì thêm, vẫn trả 204.
    $this->postJson(route('webhooks.momo'), momoKetQua($payment, 0))->assertNoContent();
    expect($payment->fresh()->txn_status)->toBe(PaymentTxnStatus::ThanhCong);
});

it('CR-260: MoMo báo thất bại thì khoản thất bại, đơn vẫn chờ thanh toán lại', function () {
    Http::fake(['test-payment.momo.vn/*' => Http::response(['resultCode' => 0, 'payUrl' => 'https://test-payment.momo.vn/pay'])]);
    $order = MH::dat($this->a, $this->d['k1']);
    $this->signIn($this->a)->post(route('student.orders.payments.store', $order), ['terms' => 1, 'gateway' => 'MOMO']);
    $payment = $order->payments()->sole();
    $this->get(route('payments.momo.return', momoKetQua($payment, 1006)))->assertRedirect();
    expect($payment->fresh()->txn_status)->toBe(PaymentTxnStatus::ThatBai)
        ->and(Order::query()->find($order->id)?->status->value)->toBe('CHO_THANH_TOAN');
});

it('CR-260: chưa có khóa MoMo thì kênh tắt, chọn MoMo vẫn về cổng mô phỏng', function () {
    config(['services.momo.secret_key' => null]);
    $order = MH::dat($this->a, $this->d['k1']);
    $this->signIn($this->a)->get(route('student.orders.show', $order))->assertInertia(fn ($p) => $p->where('momo_enabled', false));
    $this->post(route('student.orders.payments.store', $order), ['terms' => 1, 'gateway' => 'MOMO']);
    $payment = $order->payments()->sole();
    expect($payment->channel)->toBe(PaymentChannel::MoPhong);
});
