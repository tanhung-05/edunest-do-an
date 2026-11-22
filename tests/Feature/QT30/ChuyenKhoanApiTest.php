<?php

use App\Actions\M28\ApiTokens;
use App\Actions\M28\BankTransfers;
use App\Enums\M01\RoleCode;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentChannel;
use App\Enums\M07\PaymentEventOutcome;
use App\Enums\M07\PaymentReconcileStatus;
use App\Enums\M07\PaymentTxnStatus;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use App\Models\M07\PaymentEvent;
use App\Models\M11\Setting;
use App\Models\M28\BankTransferReview;
use App\Support\DomainRejection;
use App\Support\M16\DeviceRegistry;
use Illuminate\Support\Facades\DB;
use Tests\Support\HocTap;
use Tests\Support\MuaHang as MH;

pest()->group('P8');

function ckGui(string $route, array $data, ?string $signature = null)
{
    $payload = json_encode($data, JSON_THROW_ON_ERROR);

    return test()->call('POST', route($route), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_BANK_SIGNATURE' => $signature ?? BankTransfers::sign($payload),
    ], $payload);
}

function ckNguon(Order $order, string $code, int $seq, ?int $amount = null, ?string $memo = null)
{
    return ckGui('webhooks.bank-sim.transactions', ['bank_code' => $code, 'memo' => $memo ?? $order->public_code, 'amount' => $amount ?? $order->total, 'seq' => $seq]);
}

function ckXacNhan(Order $order, string $code, ?int $amount = null, ?string $memo = null, ?string $signature = null)
{
    return ckGui('webhooks.bank-sim.confirmations', ['transaction_code' => $code, 'result' => 'THANH_CONG',
        'received_amount' => $amount ?? $order->total, 'memo' => $memo ?? strtolower($order->public_code)], $signature);
}

beforeEach(function () {
    $this->d = MH::d7();
    $this->a = $this->d['a'];
    $this->k = $this->d['k1'];
    MH::luc('10:00:00');
    $this->order = MH::dat($this->a, $this->k);
    $this->tk = app(BankTransfers::class);
});

afterEach(function () {
    $this->travelBack();
});

it('QT-30 bước 1–2 X1 Mở QR cần đồng ý điều khoản; nội dung là mã đơn, hạn bằng hạn đơn; người khác bị chặn', function () {
    expect(fn () => $this->tk->open($this->a, $this->order, false))->toThrow(DomainRejection::class)
        ->and(BankTransfers::view($this->order->fresh()))->toBeNull();
    $this->signIn($this->d['b'])->post(route('student.orders.transfer.store', $this->order), ['terms' => 1])->assertForbidden();
    $this->signIn($this->a)->post(route('student.orders.transfer.store', $this->order), ['terms' => 1])->assertRedirect();
    $view = BankTransfers::view($this->order->fresh());
    expect($view['memo'])->toBe($this->order->public_code)->and($view['amount'])->toBe($this->order->total)
        ->and($view['qr_svg'])->toContain('<svg')
        ->and($view['expires_at'])->toBe($this->order->expires_at->toIso8601String());
});

it('CR-178 bước 3b–6 Nguồn ghi liên kết rồi xác nhận HMAC qua bước ghi chung: HOP_LE, cấp quyền, hoàn tất; gửi lại không ghi thêm', function () {
    $this->tk->open($this->a, $this->order, true);
    ckNguon($this->order, 'NH-0001', 1)->assertOk()->assertJson(['status' => 'LINKED']);
    ckNguon($this->order, 'NH-0001', 1)->assertJson(['status' => 'EXISTING']);
    $p = Payment::query()->sole();
    expect($p->channel)->toBe(PaymentChannel::ChuyenKhoan)->and($p->txn_status)->toBe(PaymentTxnStatus::KhoiTao)->and($p->source_seq)->toBe(1);

    ckXacNhan($this->order, 'NH-0001')->assertOk()->assertJson(['outcome' => 'DA_GHI']);
    ckXacNhan($this->order, 'NH-0001')->assertOk()->assertJson(['outcome' => 'TRUNG_LAP']);
    expect($p->fresh()->reconcile_status)->toBe(PaymentReconcileStatus::HopLe)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::DangCapQuyen);
    MH::chayCap();
    expect($this->order->fresh()->status)->toBe(OrderStatus::HoanTat)
        ->and(DB::table('enrollments')->where('user_id', $this->a->id)->where('course_id', $this->k->id)->count())->toBe(1);
});

it('X2 X3 Chữ ký sai bị từ chối trước tra cứu; mã chưa liên kết bị từ chối kể cả nội dung đúng đơn; sau 3b gửi lại được xét', function () {
    ckGui('webhooks.bank-sim.transactions', ['bank_code' => 'NH-X', 'memo' => $this->order->public_code, 'amount' => 1, 'seq' => 1], 'sai')->assertForbidden();
    ckXacNhan($this->order, 'NH-0002', signature: 'sai')->assertForbidden();
    ckXacNhan($this->order, 'NH-0002')->assertNotFound()->assertJson(['outcome' => 'TU_CHOI_MA_LA']);
    expect(Payment::query()->count())->toBe(0)->and(PaymentEvent::query()->where('outcome', PaymentEventOutcome::TuChoiChuKy)->count())->toBe(1);
    ckNguon($this->order, 'NH-0002', 1);
    ckXacNhan($this->order, 'NH-0002')->assertOk()->assertJson(['outcome' => 'DA_GHI']);
});

it('X4 CR-179 Nội dung lệch: không khớp đơn nào thì giữ bản chờ (che dữ liệu); trỏ đơn khác thì từ chối, không bản chờ; Giáo vụ xem có nhật ký', function () {
    $other = MH::dat($this->d['b'], $this->k);
    ckNguon($this->order, 'NH-0003', 1);
    ckXacNhan($this->order, 'NH-0003', memo: 'chuyen tien 0912345678 an@mail.com')->assertStatus(409)->assertJson(['outcome' => 'CHO_TRA_SOAT']);
    ckXacNhan($this->order, 'NH-0003', memo: $other->public_code)->assertStatus(409)->assertJson(['outcome' => 'KHONG_KHOP_DON_KHAC']);
    $review = BankTransferReview::query()->sole();
    expect($review->memo_masked)->not->toContain('0912345678')->not->toContain('an@mail.com')
        ->and(Payment::query()->sole()->txn_status)->toBe(PaymentTxnStatus::KhoiTao)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::ChoThanhToan);

    $gv = taiKhoan('gvu-ck@edunest.test', RoleCode::GiaoVu);
    $this->signIn($gv)->get(route('academic.bank-reviews.index'))->assertInertia(fn ($p) => $p->has('reviews', 1)
        ->where('reviews.0.amount', $this->order->total));
    expect(DB::table('audit_logs')->where('action', 'BANK_REVIEW_VIEWED')->count())->toBe(1);
    $this->signIn($this->a)->get(route('academic.bank-reviews.index'))->assertForbidden();
});

it('CR-178 CR-104 Lần chuyển thứ hai theo thứ tự nguồn luôn CAN_HOAN kể cả xác nhận đến trước; sai tiền giữ đơn chờ', function () {
    ckNguon($this->order, 'NH-0012', 2);
    ckNguon($this->order, 'NH-0011', 1);
    ckXacNhan($this->order, 'NH-0012')->assertOk();
    $second = Payment::query()->where('transaction_code', 'NH-0012')->sole();
    expect($second->reconcile_status)->toBe(PaymentReconcileStatus::CanHoan)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::ChoThanhToan);
    ckXacNhan($this->order, 'NH-0011', amount: $this->order->total - 1)->assertOk();
    expect(Payment::query()->where('transaction_code', 'NH-0011')->sole()->reconcile_status)->toBe(PaymentReconcileStatus::CanHoan)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::ChoThanhToan);
});

it('M28 API Token gắn thiết bị: cấp, dùng, giới hạn thiết bị, đăng xuất thiết bị từ web làm token mất hiệu lực', function () {
    $this->post(route('api.v1.tokens.store'), ['email' => 'a@edunest.test', 'password' => 'sai', 'device_name' => 'Pixel'])->assertStatus(422);
    $staff = taiKhoan('gv-api@edunest.test', RoleCode::GiaoVien);
    $this->postJson(route('api.v1.tokens.store'), ['email' => $staff->email, 'password' => 'password', 'device_name' => 'Pixel'])->assertForbidden();

    $issued = app(ApiTokens::class)->issue('a@edunest.test', 'password', 'Pixel 8');
    $auth = ['Authorization' => 'Bearer '.$issued['token'], 'Accept' => 'application/json'];
    $this->getJson(route('api.v1.me'), $auth)->assertOk()->assertJsonPath('data.email', 'a@edunest.test');
    $this->getJson(route('api.v1.courses.index'), $auth)->assertOk()->assertJsonCount(0, 'data');

    Setting::query()->updateOrCreate(['key' => 'device.max_active'], ['value' => 1]);
    expect(fn () => app(ApiTokens::class)->issue('a@edunest.test', 'password', 'iPad'))->toThrow(DomainRejection::class);

    DeviceRegistry::revoke($this->a, $issued['device_id']);
    app('auth')->forgetGuards();
    $this->getJson(route('api.v1.me'), $auth)->assertUnauthorized();
    $this->getJson(route('api.v1.me'))->assertUnauthorized();
});

it('M28 API Bài học theo cùng quyền web: khóa có ghi danh thấy bài, khóa khác bị chặn; đăng xuất xóa token', function () {
    $h = new HocTap;
    $issued = app(ApiTokens::class)->issue($h->a->email, 'password', 'Pixel');
    $auth = ['Authorization' => 'Bearer '.$issued['token'], 'Accept' => 'application/json'];
    $this->getJson(route('api.v1.courses.lessons.index', $h->course), $auth)->assertOk()->assertJsonPath('data.0.title', 'Bài đọc');
    $this->getJson(route('api.v1.courses.lessons.show', [$h->course, $h->read]), $auth)->assertOk()->assertJsonPath('data.title', 'Bài đọc');
    $this->postJson(route('api.v1.courses.lessons.complete', [$h->course, $h->read]), [], $auth)->assertOk();
    $this->getJson(route('api.v1.courses.lessons.index', $this->k), $auth)->assertForbidden();
    $this->deleteJson(route('api.v1.tokens.destroy'), [], $auth)->assertNoContent();
    expect(DB::table('personal_access_tokens')->count())->toBe(0);
});

it('PWA có manifest, service worker và trang ngoại tuyến', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);
    expect($manifest['name'])->toBe('EduNest')->and($manifest['display'])->toBe('standalone')
        ->and(file_exists(public_path('sw.js')))->toBeTrue()->and(file_exists(public_path('offline.html')))->toBeTrue();
    $this->get(route('home'))->assertSee('manifest.webmanifest', false);
});
