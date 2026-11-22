<?php

use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M07\CancelOrder;
use App\Actions\M07\ClaimGrantAttempt;
use App\Actions\M07\ExpireOrder;
use App\Actions\M07\PlaceOrder;
use App\Actions\M07\RecoverOrderProcessing;
use App\Actions\M08\GrantOrderItem;
use App\Enums\M01\RoleCode;
use App\Enums\M07\OrderItemStatus as DD;
use App\Enums\M07\OrderStatus as DH;
use App\Enums\M07\PaymentEventOutcome as XN;
use App\Enums\M07\PaymentEventResult as KQ;
use App\Enums\M07\PaymentReconcileStatus as DS;
use App\Enums\M07\PaymentTxnStatus as GD;
use App\Events\M07\PaymentReconciled;
use App\Models\M01\Role;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use App\Models\M07\PaymentEvent;
use App\Models\M08\Enrollment;
use App\Models\M11\AuditLog;
use App\Models\M11\Setting;
use App\Support\DomainRejection;
use App\Support\M07\PaymentConfirmation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\MuaHang as MH;

pest()->group('P3');

beforeEach(function () {
    $this->d = MH::d7();
    $this->a = $this->d['a'];
    $this->b = $this->d['b'];
    $this->k = $this->d['k1'];
    MH::luc('10:00:00');
});

afterEach(function () {
    $this->travelBack();
});

function donP3(object $test): Order
{
    return MH::dat($test->a, $test->k);
}

function khoanP3(object $test, ?Order $order = null): Payment
{
    return MH::khoiTao($test->a, $order ?? donP3($test));
}

it('AC-07-01 Đặt, đồng ý điều khoản, xác nhận và cấp ghi danh', function () {
    $this->signIn($this->a)->get(route('public.courses.buy', $this->k->slug))->assertOk();
    expect(Order::query()->count())->toBe(0);
    $this->post(route('student.orders.store', $this->k->slug))->assertRedirect();
    $order = Order::query()->sole();
    $this->post(route('student.orders.payments.store', $order), ['terms' => 1])->assertRedirect();
    $payment = $order->payments()->sole();
    $this->post(route('gateway.simulated.confirm', $payment), ['result' => 'THANH_CONG'])->assertRedirect();
    expect($payment->fresh()->txn_status)->toBe(GD::KhoiTao);
    $body = MH::payload($payment);
    Http::fake(fn ($request) => Http::response(
        $this->call('POST', route('webhooks.payment-sim'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYMENT_SIGNATURE' => $request->header('X-Payment-Signature')[0],
        ], $request->body())->assertOk()->json(),
    ));
    expect($body)->toBe(PaymentConfirmation::simulated($payment, KQ::ThanhCong));
    Queue::connection('database')->pop('default')->fire();
    MH::luc('10:00:40');
    expect(MH::chayCap())->toBeTrue()
        ->and($order->fresh()->status)->toBe(DH::HoanTat)
        ->and(MH::dong($order)->status)->toBe(DD::DaCap)
        ->and(Enrollment::query()->sole()->het_han_luc->equalTo(now()->addYear()))->toBeTrue();
});

it('AC-07-02 Ghi danh còn hạn chặn tạo đơn mua mới', function () {
    $enrollment = MH::ghiDanh($this->a, $this->k);
    $this->signIn($this->a)->post(route('student.orders.store', $this->k->slug))
        ->assertRedirect(route('student.courses.index', ['course' => $this->k->id]));
    expect(app(PlaceOrder::class)->handle($this->a, $this->k)->id)->toBe($enrollment->id)
        ->and(Order::query()->count())->toBe(0);
});

it('AC-07-03 Mở lại đơn chờ, giữ hạn đã chụp', function () {
    $order = donP3($this);
    MH::luc('10:05:00');
    $this->signIn($this->a)->get(route('public.courses.buy', $this->k->slug))->assertRedirect(route('student.orders.show', $order));
    expect(donP3($this)->expires_at->equalTo($order->expires_at))->toBeTrue()
        ->and(Order::query()->count())->toBe(1);
});

it('AC-07-04 Mở lại đơn cần xử lý, không thanh toán thêm', function () {
    $order = donP3($this);
    $order->update(['status' => DH::CanXuLy]);
    $this->signIn($this->a)->get(route('public.courses.buy', $this->k->slug))->assertRedirect(route('student.orders.show', $order));
    expect($this->get(route('student.orders.show', $order))->assertOk()->viewData('page')['props']['order']['can_pay'])->toBeFalse()
        ->and(Order::query()->count())->toBe(1)->and(Payment::query()->count())->toBe(0);
});

it('AC-07-05 Ghi danh hết hạn chặn mua và hiện nhãn trước P5', function () {
    MH::ghiDanh($this->a, $this->k, ['het_han_luc' => now()]);
    $this->signIn($this->a)->post(route('student.orders.store', $this->k->slug))->assertRedirect();
    $props = $this->get(route('student.courses.index'))->assertOk()->viewData('page')['props'];
    expect($props['courses'][0]['validity_label'])->toBe('Đã hết hạn truy cập')->and(Order::query()->count())->toBe(0);
});

it('AC-07-06 Ghi danh thu hồi chặn mua và hướng liên hệ Giáo vụ', function () {
    MH::ghiDanh($this->a, $this->k, ['revoked_at' => now()]);
    $this->signIn($this->a)->post(route('student.orders.store', $this->k->slug))->assertRedirect();
    $props = $this->get(route('public.courses.show', $this->k->slug))->assertOk()->viewData('page')['props'];
    expect($props['cta']['label'])->toBe('Liên hệ giáo vụ')->and(Order::query()->count())->toBe(0);
});

it('AC-07-07 Học viên chưa xác minh vẫn xem khóa, chưa đặt được', function () {
    $this->a->forceFill(['email_verified_at' => null])->save();
    $this->signIn($this->a)->get(route('public.courses.show', $this->k->slug))->assertOk();
    $this->post(route('student.orders.store', $this->k->slug))->assertRedirect(route('verification.notice'));
    expect(Order::query()->count())->toBe(0);
});

it('AC-07-08 Khóa ngừng bán không tạo đơn mới', function () {
    $this->signIn($this->a)->postJson(route('student.orders.store', $this->d['k5']->slug))->assertUnprocessable();
    expect(Order::query()->count())->toBe(0)->and(Payment::query()->count())->toBe(0);
});

it('AC-07-09 Hủy đơn chờ, đặt lại tạo đơn mới', function () {
    $order = donP3($this);
    $this->signIn($this->a)->post(route('student.orders.cancel', $order))->assertRedirect();
    $next = donP3($this);
    expect($order->fresh()->status)->toBe(DH::DaHuy)->and($next->id)->not->toBe($order->id)
        ->and($next->status)->toBe(DH::ChoThanhToan)->and($order->payments()->count())->toBe(0);
});

it('AC-07-10 Quét đúng hạn vẫn chờ, sau hạn mới hết hạn', function () {
    $order = donP3($this);
    MH::luc('10:30:00');
    $this->artisan('edunest:expire-overdue-orders')->assertSuccessful();
    expect($order->fresh()->status)->toBe(DH::ChoThanhToan);
    MH::luc('10:31:00');
    $this->artisan('edunest:expire-overdue-orders')->assertSuccessful();
    expect($order->fresh()->status)->toBe(DH::HetHan)->and(Enrollment::query()->count())->toBe(0);
});

it('AC-07-11 Đơn đang cấp quyền không bị quét hết hạn', function () {
    $payment = khoanP3($this);
    MH::xacNhan($payment);
    MH::luc('11:00:00');
    $this->artisan('edunest:expire-overdue-orders')->assertSuccessful();
    expect($payment->order()->first()->status)->toBe(DH::DangCapQuyen)->and($payment->fresh()->reconcile_status)->toBe(DS::HopLe);
});

it('AC-07-12 Đơn đang cấp quyền không hiện nút hủy và không hủy được', function () {
    $payment = khoanP3($this);
    MH::xacNhan($payment);
    $order = $payment->order()->firstOrFail();
    expect($this->signIn($this->a)->get(route('student.orders.show', $order))->assertOk()->viewData('page')['props']['order']['can_cancel'])->toBeFalse();
    $this->postJson(route('student.orders.cancel', $order))->assertUnprocessable();
    expect($order->fresh()->status)->toBe(DH::DangCapQuyen);
});

it('AC-07-13 Phải đồng ý điều khoản và đơn còn hiệu lực', function () {
    $order = donP3($this);
    $this->signIn($this->a)->postJson(route('student.orders.payments.store', $order))->assertUnprocessable()->assertJsonValidationErrors('terms');
    MH::luc('10:40:00');
    $this->postJson(route('student.orders.payments.store', $order), ['terms' => true])->assertUnprocessable();
    expect($order->fresh()->status)->toBe(DH::HetHan)->and($order->payments()->count())->toBe(0);
});

it('AC-07-14 Webhook thiếu hoặc sai chữ ký bị chặn', function () {
    $payment = khoanP3($this);
    foreach ([[], ['HTTP_X_PAYMENT_SIGNATURE' => 'sai']] as $headers) {
        $this->call('POST', route('webhooks.payment-sim'), [], [], [], $headers, MH::payload($payment))->assertForbidden();
    }
    expect($payment->fresh()->txn_status)->toBe(GD::KhoiTao)->and($payment->order()->first()->status)->toBe(DH::ChoThanhToan)
        ->and(Enrollment::query()->count())->toBe(0);
});

it('AC-07-15 Gửi lại xác nhận sau hoàn tất giữ nguyên hạn và khoản', function () {
    $payment = khoanP3($this);
    MH::xacNhan($payment);
    MH::chayCap();
    $deadline = Enrollment::query()->sole()->het_han_luc;
    MH::luc('10:05:00');
    $this->signIn($this->a)->post(route('gateway.simulated.resend', $payment), ['result' => 'THAT_BAI'])->assertRedirect();
    expect(MH::soViec('default'))->toBe(1)
        ->and(MH::xacNhan($payment)->outcome)->toBe(XN::TrungLap)
        ->and($payment->order()->first()->status)->toBe(DH::HoanTat)
        ->and($payment->order()->first()->payments()->count())->toBe(1)
        ->and(Enrollment::query()->sole()->het_han_luc->equalTo($deadline))->toBeTrue();
});

it('AC-07-17 Bốn lần lỗi mới chốt cấp lỗi, không tự chạy thêm', function () {
    $payment = khoanP3($this);
    MH::giaLapLoi(true);
    MH::luc('10:00:30');
    MH::xacNhan($payment);
    foreach (['10:00:30', '10:01:30', '10:06:30', '10:21:30'] as $i => $time) {
        MH::luc($time);
        expect(MH::dong($payment->order)->status)->toBe(DD::ChuaCap)->and(MH::chayCap())->toBeTrue()
            ->and(MH::dong($payment->order)->grant_attempts)->toBe($i + 1);
    }
    MH::luc('10:40:00');
    expect(MH::dong($payment->order)->status)->toBe(DD::CapLoi)->and(MH::soViec())->toBe(0)
        ->and($payment->order()->first()->status)->toBe(DH::CanXuLy)->and(Enrollment::query()->count())->toBe(0);
});

it('AC-07-18 Thử lại sau 60 giây, hạn tính từ lúc cấp', function () {
    $payment = khoanP3($this);
    MH::giaLapLoi(true);
    MH::luc('10:00:30');
    MH::xacNhan($payment);
    expect(MH::chayCap())->toBeTrue();
    MH::giaLapLoi(false);
    MH::luc('10:01:29');
    expect(MH::chayCap())->toBeFalse();
    MH::luc('10:01:30');
    expect(MH::chayCap())->toBeTrue()->and($payment->order()->first()->status)->toBe(DH::HoanTat)
        ->and(Enrollment::query()->sole()->het_han_luc->equalTo(now()->addYear()))->toBeTrue();
});

it('AC-07-19 Thu thêm sau hoàn tất đưa đơn về cần xử lý, giữ hạn', function () {
    $first = khoanP3($this);
    $second = khoanP3($this, $first->order);
    MH::xacNhan($first);
    MH::chayCap();
    $deadline = Enrollment::query()->sole()->het_han_luc;
    MH::luc('10:05:00');
    MH::xacNhan($second);
    expect($second->fresh()->reconcile_status)->toBe(DS::CanHoan)->and($second->order()->first()->status)->toBe(DH::CanXuLy)
        ->and(Enrollment::query()->sole()->het_han_luc->equalTo($deadline))->toBeTrue();
});

it('AC-07-20 Thu thêm khi đang cấp chỉ chuyển cần xử lý sau khi cấp', function () {
    $first = khoanP3($this);
    $second = khoanP3($this, $first->order);
    MH::xacNhan($first);
    MH::xacNhan($second);
    expect($second->fresh()->reconcile_status)->toBe(DS::CanHoan)
        ->and($second->order()->first()->status)->toBe(DH::DangCapQuyen);
    MH::chayCap();
    expect($second->order()->first()->status)->toBe(DH::CanXuLy)->and(Enrollment::query()->count())->toBe(1);
});

it('AC-07-21 Thu thêm khi cấp lỗi vẫn chỉ có một khoản hợp lệ', function () {
    $first = khoanP3($this);
    $second = khoanP3($this, $first->order);
    MH::xacNhan($first);
    MH::dong($first->order)->update(['status' => DD::CapLoi]);
    app(RecoverOrderProcessing::class)->handle($first->order_id);
    MH::xacNhan($second);
    expect($second->fresh()->reconcile_status)->toBe(DS::CanHoan)
        ->and($second->order()->first()->status)->toBe(DH::CanXuLy)
        ->and($first->order()->first()->payments()->where('reconcile_status', DS::HopLe)->count())->toBe(1);
});

it('AC-07-22 Thất bại giữ đơn chờ và cho thanh toán lại', function () {
    $payment = khoanP3($this);
    MH::xacNhan($payment, result: KQ::ThatBai);
    expect($payment->fresh()->txn_status)->toBe(GD::ThatBai)->and($payment->order()->first()->status)->toBe(DH::ChoThanhToan)
        ->and(khoanP3($this, $payment->order)->id)->not->toBe($payment->id)
        ->and(Enrollment::query()->count())->toBe(0);
});

it('AC-07-23 Thành công đến sau thất bại cùng mã', function () {
    $payment = khoanP3($this);
    MH::xacNhan($payment, result: KQ::ThatBai);
    expect(MH::xacNhan($payment)->outcome)->toBe(XN::DaGhi);
    MH::chayCap();
    expect($payment->fresh()->reconcile_status)->toBe(DS::HopLe)->and($payment->order()->first()->status)->toBe(DH::HoanTat);
});

it('AC-07-24 Khoản đến sau hết hạn đưa đơn vào cần xử lý', function () {
    $payment = khoanP3($this);
    MH::luc('10:40:00');
    app(ExpireOrder::class)->handle($payment->order_id);
    MH::xacNhan($payment);
    expect($payment->fresh()->is_late)->toBeTrue()->and($payment->fresh()->reconcile_status)->toBe(DS::CanHoan)
        ->and($payment->order()->first()->status_before_cxl)->toBe(DH::HetHan)->and(MH::soViec())->toBe(0);
});

it('AC-07-25 Khoản đến sau hủy phải hoàn', function () {
    $payment = khoanP3($this);
    app(CancelOrder::class)->handle($this->a, $payment->order);
    MH::xacNhan($payment);
    expect($payment->fresh()->reconcile_status)->toBe(DS::CanHoan)
        ->and($payment->order()->first()->status_before_cxl)->toBe(DH::DaHuy)->and(Enrollment::query()->count())->toBe(0);
});

it('AC-07-26 Hai khoản muộn đều cần hoàn trước quyết định Giáo vụ', function () {
    $first = khoanP3($this);
    $second = khoanP3($this, $first->order);
    MH::luc('11:00:00');
    MH::xacNhan($first);
    MH::xacNhan($second);
    expect($second->order()->first()->status)->toBe(DH::CanXuLy)->and($second->order()->first()->payments()->where('reconcile_status', DS::CanHoan)->count())->toBe(2);
});

it('AC-07-27 Hai khoản sai tiền giữ đơn chờ và chưa xếp cấp', function () {
    $first = khoanP3($this);
    $second = khoanP3($this, $first->order);
    MH::xacNhan($first, 400000);
    MH::xacNhan($second, 500000);
    expect($second->order()->first()->status)->toBe(DH::ChoThanhToan)->and($second->order()->first()->payments()->where('reconcile_status', DS::CanHoan)->count())->toBe(2)
        ->and(MH::soViec())->toBe(0)->and(Enrollment::query()->count())->toBe(0);
});

it('AC-07-28 Thay giá sau khi đặt không đổi giá chụp', function () {
    $order = donP3($this);
    $this->k->update(['price' => 599000]);
    $payment = khoanP3($this, $order);
    MH::xacNhan($payment);
    expect($payment->fresh()->reconcile_status)->toBe(DS::HopLe)->and($payment->requested_amount)->toBe(499000)
        ->and(MH::dong($order)->final_amount)->toBe(499000);
});

it('AC-07-29 Thay số tháng sau đặt vẫn cấp theo bản chụp', function () {
    $payment = khoanP3($this);
    $this->k->update(['access_months' => 2]);
    MH::xacNhan($payment);
    MH::chayCap();
    expect(MH::dong($payment->order)->access_months)->toBe(12)->and(Enrollment::query()->sole()->het_han_luc->equalTo(now()->addYear()))->toBeTrue();
});

it('AC-07-30 Cộng theo tháng lịch, không tràn tháng', function () {
    $this->k->update(['access_months' => 1]);
    MH::luc('2027-01-31 10:00:00');
    $payment = khoanP3($this);
    MH::xacNhan($payment);
    MH::chayCap();
    expect(Enrollment::query()->sole()->het_han_luc->setTimezone('Asia/Ho_Chi_Minh')->toDateTimeString())->toBe('2027-02-28 10:00:00');
});

it('AC-07-31 Ghi danh vĩnh viễn có hạn rỗng', function () {
    $this->k->update(['access_months' => null]);
    $payment = khoanP3($this);
    MH::xacNhan($payment);
    MH::chayCap();
    expect(Enrollment::query()->sole()->het_han_luc)->toBeNull();
});

it('AC-07-32 Lúc cấp chọn bản công bố hiện hành', function () {
    $payment = khoanP3($this);
    MH::xacNhan($payment);
    $version = MH::congBoMoi($this->k, $this->d['t1'], $this->d['minh'], $this->d['lan']);
    MH::chayCap();
    expect(Enrollment::query()->sole()->course_version_id)->toBe($version->id);
});

it('AC-07-33 Ngừng bán không khóa thanh toán của đơn đã đặt', function () {
    $order = donP3($this);
    app(ChangeSaleStatus::class)->stop($this->d['lan'], $this->k);
    expect(fn () => MH::dat($this->b, $this->k))->toThrow(DomainRejection::class);
    MH::xacNhan(khoanP3($this, $order));
    MH::chayCap();
    expect($order->fresh()->status)->toBe(DH::HoanTat);
});

it('AC-07-34 Giáo viên kiêm học viên mua khóa soạn được', function () {
    $actor = $this->d['t1'];
    $actor->roles()->attach(Role::idFor(RoleCode::HocVien), ['assigned_at' => now()]);
    $order = MH::dat($actor, $this->k);
    MH::xacNhan(MH::khoiTao($actor, $order));
    MH::chayCap();
    expect(Enrollment::query()->sole()->user_id)->toBe($actor->id);
});

it('AC-07-35 Đặt cùng khóa trả đúng một đơn và một dòng', function () {
    $first = donP3($this);
    expect(donP3($this)->id)->toBe($first->id)->and(Order::query()->count())->toBe(1)->and($first->items()->count())->toBe(1);
});

it('AC-07-36 Hai khoản đúng tiền chỉ cấp một ghi danh', function () {
    $first = khoanP3($this);
    $second = khoanP3($this, $first->order);
    MH::xacNhan($first);
    MH::xacNhan($second);
    MH::chayCap();
    expect($first->fresh()->reconcile_status)->toBe(DS::HopLe)->and($second->fresh()->reconcile_status)->toBe(DS::CanHoan)
        ->and($first->order()->first()->status)->toBe(DH::CanXuLy)->and(Enrollment::query()->count())->toBe(1);
});

it('AC-07-37 Khách phải đăng nhập, Giáo vụ không mua hộ', function () {
    $this->get(route('public.courses.buy', $this->k->slug))->assertRedirect(route('login'));
    $this->signIn($this->d['lan'])->postJson(route('student.orders.store', $this->k->slug))->assertForbidden();
    expect(Order::query()->count())->toBe(0);
});

it('AC-07-38 Học viên khác không xem được đơn hoặc khoản', function () {
    $payment = khoanP3($this);
    $this->signIn($this->b)->get(route('student.orders.show', $payment->order))->assertForbidden();
    $this->get(route('gateway.simulated.show', $payment))->assertForbidden();
});

it('AC-07-39 Học viên khác không hủy được đơn', function () {
    $order = donP3($this);
    $this->signIn($this->b)->postJson(route('student.orders.cancel', $order))->assertForbidden();
    expect($order->fresh()->status)->toBe(DH::ChoThanhToan);
});

it('AC-07-40 Khác chủ không thanh toán, gửi hay gửi lại xác nhận', function () {
    $payment = khoanP3($this);
    $this->signIn($this->b)->postJson(route('student.orders.payments.store', $payment->order), ['terms' => true])->assertForbidden();
    $this->postJson(route('gateway.simulated.confirm', $payment), ['result' => 'THANH_CONG'])->assertForbidden();
    $this->postJson(route('gateway.simulated.resend', $payment))->assertForbidden();
    expect($payment->fresh()->txn_status)->toBe(GD::KhoiTao)->and(MH::soViec('default'))->toBe(0);
});

it('AC-07-41 Không hủy đơn đã hoàn tất, hết hạn hoặc cần xử lý', function () {
    $order = donP3($this);
    $this->signIn($this->a);
    foreach ([DH::HoanTat, DH::HetHan, DH::CanXuLy] as $status) {
        $order->update(['status' => $status]);
        $this->postJson(route('student.orders.cancel', $order))->assertUnprocessable();
        expect($order->fresh()->status)->toBe($status);
    }
});

it('AC-07-42 Dừng worker không làm mất việc cấp quyền', function () {
    $payment = khoanP3($this);
    MH::xacNhan($payment);
    MH::luc('11:00:00');
    $this->artisan('edunest:ops-status')->expectsOutput('Worker: không chạy hoặc chưa có nhịp')->assertSuccessful();
    expect($payment->order()->first()->status)->toBe(DH::DangCapQuyen);
    MH::luc('11:00:40');
    MH::chayCap();
    expect($payment->order()->first()->status)->toBe(DH::HoanTat)
        ->and(Enrollment::query()->sole()->het_han_luc->equalTo(now()->addYear()))->toBeTrue();
});

it('AC-07-46 Bật giả lập lỗi chỉ dành cho Quản trị trong demo', function () {
    config(['app.demo' => true]);
    $route = route('admin.settings.grant-failure-mode.update');
    foreach ([$this->d['lan'], $this->a] as $user) {
        $this->signIn($user)->patchJson($route, ['enabled' => true])->assertForbidden();
    }
    $this->signIn(taiKhoan('quantri@edunest.test', RoleCode::QuanTri))->patch($route, ['enabled' => true])->assertRedirect();
    expect(Setting::grantFailureMode())->toBeTrue();
    config(['app.demo' => false]);
    $this->patchJson($route, ['enabled' => true])->assertForbidden();
    expect(Setting::grantFailureMode())->toBeFalse();
});

it('AC-07-47 Giáo vụ không hủy đơn thay học viên', function () {
    $order = donP3($this);
    $this->signIn($this->d['lan'])->postJson(route('student.orders.cancel', $order))->assertForbidden();
    expect($order->fresh()->status)->toBe(DH::ChoThanhToan);
});

it('AC-07-48 Khách phải đăng nhập và Giáo viên không xem được đơn', function () {
    $url = route('student.orders.show', donP3($this));
    $this->get($url)->assertRedirect(route('login'));
    $this->signIn($this->d['t1'])->get($url)->assertForbidden();
});

it('AC-07-49 Giáo vụ không khởi tạo khoản của học viên', function () {
    $order = donP3($this);
    $this->signIn($this->d['lan'])->postJson(route('student.orders.payments.store', $order), ['terms' => true])->assertForbidden();
    expect($order->payments()->count())->toBe(0);
});

it('AC-07-50 Giáo viên không gửi lại xác nhận của học viên', function () {
    $payment = khoanP3($this);
    MH::xacNhan($payment);
    $this->signIn($this->d['t1'])->postJson(route('gateway.simulated.resend', $payment))->assertForbidden();
    expect(MH::soViec('default'))->toBe(0);
});

it('AC-07-51 Mua khóa chưa bật bán trả 404', function () {
    $this->signIn($this->a)->postJson(route('student.orders.store', $this->d['k4']->slug))->assertNotFound();
    expect(Order::query()->count())->toBe(0);
});

it('AC-07-52 Khoản mới đúng tiền sau thất bại vẫn cấp được quyền', function () {
    $first = khoanP3($this);
    MH::xacNhan($first, result: KQ::ThatBai);
    $second = khoanP3($this, $first->order);
    MH::xacNhan($second);
    MH::chayCap();
    expect($first->fresh()->txn_status)->toBe(GD::ThatBai)->and($second->fresh()->reconcile_status)->toBe(DS::HopLe)
        ->and($second->order()->first()->status)->toBe(DH::HoanTat);
});

it('AC-07-53 Đúng giây hết hạn khoản vẫn hợp lệ', function () {
    $payment = khoanP3($this);
    MH::luc('10:30:00');
    MH::xacNhan($payment);
    MH::chayCap();
    expect($payment->fresh()->reconcile_status)->toBe(DS::HopLe)->and($payment->order()->first()->status)->toBe(DH::HoanTat);
});

it('AC-07-54 Sau hạn một giây phải hoàn dù chưa quét', function () {
    $payment = khoanP3($this);
    MH::luc('10:30:01');
    MH::xacNhan($payment);
    expect($payment->fresh()->reconcile_status)->toBe(DS::CanHoan)->and($payment->order()->first()->status_before_cxl)->toBe(DH::HetHan)
        ->and($payment->order()->first()->expired_at->equalTo(now()))->toBeTrue()->and(MH::soViec())->toBe(0);
    $this->artisan('edunest:expire-overdue-orders')->assertSuccessful();
    expect($payment->order()->first()->status)->toBe(DH::CanXuLy);
});

it('AC-07-56 Thất bại của khoản khác không lùi đơn đã hoàn tất', function () {
    $first = khoanP3($this);
    $second = khoanP3($this, $first->order);
    MH::xacNhan($second);
    MH::chayCap();
    MH::xacNhan($first, result: KQ::ThatBai);
    expect($first->fresh()->txn_status)->toBe(GD::ThatBai)->and($first->order()->first()->status)->toBe(DH::HoanTat)
        ->and($second->fresh()->reconcile_status)->toBe(DS::HopLe);
});

it('AC-07-57 Khoản muộn sai tiền vẫn vào cần xử lý', function () {
    $payment = khoanP3($this);
    MH::luc('10:45:00');
    MH::xacNhan($payment, 400000);
    expect($payment->fresh()->is_late)->toBeTrue()->and($payment->fresh()->received_amount)->toBe(400000)
        ->and($payment->fresh()->reconcile_status)->toBe(DS::CanHoan)->and($payment->order()->first()->status)->toBe(DH::CanXuLy);
});

it('AC-07-58 Mã lạ chỉ ghi dấu vết kỹ thuật', function () {
    $order = donP3($this);
    $count = AuditLog::query()->count();
    $payload = MH::payload('GD-999');
    $this->call('POST', route('webhooks.payment-sim'), [], [], [], ['HTTP_X_PAYMENT_SIGNATURE' => PaymentConfirmation::sign($payload)], $payload)->assertNotFound();
    expect($order->fresh()->status)->toBe(DH::ChoThanhToan)->and($order->payments()->count())->toBe(0)
        ->and(PaymentEvent::query()->sole()->outcome)->toBe(XN::TuChoiMaLa)->and(AuditLog::query()->count())->toBe($count);
});

it('AC-07-59 Lần thử cuối còn ngân sách và cấp thành công', function () {
    $payment = khoanP3($this);
    MH::giaLapLoi(true);
    MH::luc('10:00:30');
    MH::xacNhan($payment);
    foreach (['10:00:30', '10:01:30', '10:06:30'] as $time) {
        MH::luc($time);
        expect(MH::chayCap())->toBeTrue();
    }
    MH::giaLapLoi(false);
    MH::luc('10:21:30');
    expect(MH::chayCap())->toBeTrue()->and(MH::dong($payment->order)->grant_attempts)->toBe(4)
        ->and($payment->order()->first()->status)->toBe(DH::HoanTat);
});

it('AC-07-61 Đã có khoản sai tiền vẫn thanh toán lại và cấp được', function () {
    $first = khoanP3($this);
    MH::xacNhan($first, 400000);
    $second = khoanP3($this, $first->order);
    MH::xacNhan($second);
    MH::chayCap();
    expect($first->fresh()->reconcile_status)->toBe(DS::CanHoan)->and($second->fresh()->reconcile_status)->toBe(DS::HopLe)
        ->and($second->order()->first()->status)->toBe(DH::CanXuLy)->and(Enrollment::query()->count())->toBe(1);
});

it('AC-07-62 Mua ngay khi đang cấp quyền mở đơn hiện có', function () {
    $payment = khoanP3($this);
    MH::xacNhan($payment);
    $this->signIn($this->a)->get(route('public.courses.buy', $this->k->slug))->assertRedirect(route('student.orders.show', $payment->order));
    expect($this->get(route('student.orders.show', $payment->order))->assertOk()->viewData('page')['props']['order']['can_pay'])->toBeFalse();
});

it('AC-07-63 Quét hết hạn đơn sai tiền giữ nguyên khoản cần hoàn', function () {
    $payment = khoanP3($this);
    MH::xacNhan($payment, 400000);
    MH::luc('10:31:00');
    $this->artisan('edunest:expire-overdue-orders')->assertSuccessful();
    expect($payment->order()->first()->status)->toBe(DH::HetHan)->and($payment->fresh()->reconcile_status)->toBe(DS::CanHoan);
});

it('AC-07-64 Không lùi khoản thành công khi nhận thất bại', function () {
    $payment = khoanP3($this);
    MH::xacNhan($payment);
    MH::chayCap();
    expect(MH::xacNhan($payment, result: KQ::ThatBai)->outcome)->toBe(XN::TrungLap)
        ->and($payment->fresh()->txn_status)->toBe(GD::ThanhCong)->and($payment->order()->first()->status)->toBe(DH::HoanTat);
});

it('AC-07-66 Giáo viên và Quản trị không có vai Học viên không mua được', function () {
    foreach ([$this->d['t1'], taiKhoan('quantri@edunest.test', RoleCode::QuanTri)] as $user) {
        $this->signIn($user)->postJson(route('student.orders.store', $this->k->slug))->assertForbidden();
    }
    expect(Order::query()->count())->toBe(0);
});

it('AC-07-67 Xác nhận lặp bổ sung số thực thu và đối soát thiếu', function () {
    $payment = khoanP3($this);
    $payment->update(['txn_status' => GD::ThanhCong, 'succeeded_at' => now()]);
    MH::luc('10:05:00');
    expect(MH::xacNhan($payment)->outcome)->toBe(XN::TrungLap)->and(MH::soViec())->toBe(1)
        ->and($payment->fresh()->reconcile_status)->toBe(DS::HopLe)->and($payment->fresh()->received_amount)->toBe(499000);
    MH::chayCap();
    MH::xacNhan($payment);
    expect(MH::soViec())->toBe(0)->and(Enrollment::query()->count())->toBe(1);
});

it('AC-07-68 Khoản sau không phát sự kiện thanh toán hợp lệ', function () {
    $first = khoanP3($this);
    $extra = [khoanP3($this, $first->order), khoanP3($this, $first->order)];
    MH::xacNhan($first);
    MH::chayCap();
    Event::fake([PaymentReconciled::class]);
    MH::xacNhan($extra[0]);
    MH::xacNhan($extra[1], 400000);
    Event::assertNotDispatched(PaymentReconciled::class);
    expect(MH::soViec())->toBe(0)->and(Enrollment::query()->count())->toBe(1);
});

it('AC-07-69 Hai thứ tự khoản đúng và sai tiền cho cùng kết cục', function (bool $rightFirst) {
    $right = khoanP3($this);
    $wrong = khoanP3($this, $right->order);
    foreach ($rightFirst ? [[$right, 499000], [$wrong, 400000]] : [[$wrong, 400000], [$right, 499000]] as [$payment, $amount]) {
        MH::xacNhan($payment, $amount);
    }
    MH::chayCap();
    expect($right->fresh()->reconcile_status)->toBe(DS::HopLe)->and($wrong->fresh()->reconcile_status)->toBe(DS::CanHoan)
        ->and($right->order()->first()->status)->toBe(DH::CanXuLy)->and(Enrollment::query()->count())->toBe(1);
})->with([true, false]);

it('AC-07-70 Hai thứ tự hủy và khoản sai tiền phân biệt khoản muộn', function (bool $cancelFirst) {
    $payment = khoanP3($this);
    $cancel = fn () => app(CancelOrder::class)->handle($this->a, $payment->order);
    $receive = fn () => MH::xacNhan($payment, 400000);
    foreach ($cancelFirst ? [$cancel, $receive] : [$receive, $cancel] as $action) {
        $action();
    }
    expect($payment->fresh()->reconcile_status)->toBe(DS::CanHoan)->and($payment->fresh()->is_late)->toBe($cancelFirst)
        ->and($payment->order()->first()->status)->toBe($cancelFirst ? DH::CanXuLy : DH::DaHuy);
})->with([true, false]);

it('AC-07-71 Thất bại lặp không đổi mốc thất bại', function () {
    $payment = khoanP3($this);
    MH::xacNhan($payment, result: KQ::ThatBai);
    $failedAt = $payment->fresh()->failed_at;
    MH::luc('10:05:00');
    expect(MH::xacNhan($payment, result: KQ::ThatBai)->outcome)->toBe(XN::TrungLap)
        ->and($payment->fresh()->failed_at->equalTo($failedAt))->toBeTrue()->and($payment->order()->first()->status)->toBe(DH::ChoThanhToan);
});

it('AC-07-72 Chỉ Quản trị tắt được giả lập lỗi', function () {
    MH::giaLapLoi(true);
    $route = route('admin.settings.grant-failure-mode.update');
    foreach ([$this->d['lan'], $this->a, $this->d['t1']] as $user) {
        $this->signIn($user)->patchJson($route, ['enabled' => false])->assertForbidden();
        expect(Setting::grantFailureMode())->toBeTrue();
    }
    $this->signIn(taiKhoan('quantri@edunest.test', RoleCode::QuanTri))->patch($route, ['enabled' => false])->assertRedirect();
    expect(Setting::grantFailureMode())->toBeFalse();
});

it('AC-07-73 Xác nhận lặp phục hồi đúng một việc mất', function () {
    $payment = khoanP3($this);
    MH::xacNhan($payment);
    DB::table('jobs')->delete();
    expect(MH::xacNhan($payment)->outcome)->toBe(XN::TrungLap)->and(MH::soViec())->toBe(1);
    MH::chayCap();
    MH::xacNhan($payment);
    expect($payment->order()->first()->status)->toBe(DH::HoanTat)->and(MH::soViec())->toBe(0)->and(Enrollment::query()->count())->toBe(1);
});

it('AC-07-74 Quét thay việc mất và giữ việc đang chờ thử lại', function (bool $missing) {
    $payment = khoanP3($this);
    MH::giaLapLoi(true);
    MH::luc('10:00:30');
    MH::xacNhan($payment);
    MH::chayCap();
    $old = $payment->order()->first()->grant_job_id;
    if ($missing) {
        DB::table('jobs')->where('id', $old)->delete();
    }
    MH::luc('10:01:00');
    $this->artisan('edunest:recover-granting-orders')->assertSuccessful();
    expect(MH::soViec())->toBe(1)->and($payment->order()->first()->grant_job_id === $old)->toBe(! $missing)
        ->and(MH::chayCap())->toBeFalse();
    MH::giaLapLoi(false);
    MH::luc('10:01:30');
    expect(MH::chayCap())->toBeTrue()->and($payment->order()->first()->status)->toBe(DH::HoanTat)
        ->and(Enrollment::query()->count())->toBe(1);
})->with([true, false])->group('queue-db');

it('AC-07-75 Quét và gửi lại chỉ xếp một việc, giao trùng chỉ nhận một lần', function (bool $scanFirst) {
    $payment = khoanP3($this);
    MH::xacNhan($payment);
    DB::table('jobs')->delete();
    $recover = fn () => app(RecoverOrderProcessing::class)->handle($payment->order_id);
    $repeat = fn () => MH::xacNhan($payment);
    foreach ($scanFirst ? [$recover, $repeat] : [$repeat, $recover] as $action) {
        $action();
    }
    expect(MH::soViec())->toBe(1);
    $order = $payment->order()->firstOrFail();
    $claim = app(ClaimGrantAttempt::class);
    $run = $claim->handle($order->id, $order->grant_ticket, $order->grant_job_id);
    expect($claim->handle($order->id, $order->grant_ticket, $order->grant_job_id))->toBeNull();
    expect(app(GrantOrderItem::class)->handle($run))->toBeTrue();
    expect($claim->handle($order->id, $order->grant_ticket, $order->grant_job_id))->toBeNull();
    app(RecoverOrderProcessing::class)->handle($order->id, $run);
    expect($order->fresh()->status)->toBe(DH::HoanTat)->and(Enrollment::query()->count())->toBe(1);
})->with([true, false]);

it('AC-07-81 Ngừng bán ghi trước thì đặt mua bị chặn', function () {
    app(ChangeSaleStatus::class)->stop($this->d['lan'], $this->k);
    expect(fn () => MH::dat($this->a, $this->k))->toThrow(DomainRejection::class)->and(Order::query()->count())->toBe(0);
});

it('AC-07-82 Đặt đơn ghi trước vẫn cấp quyền sau khi ngừng bán', function () {
    $payment = khoanP3($this);
    app(ChangeSaleStatus::class)->stop($this->d['lan'], $this->k);
    MH::xacNhan($payment);
    MH::chayCap();
    expect($payment->order()->first()->status)->toBe(DH::HoanTat)->and(Enrollment::query()->sole()->user_id)->toBe($this->a->id);
    expect(fn () => MH::dat($this->b, $this->k))->toThrow(DomainRejection::class);
});

function dungTruocChot(object $test, bool $extra = false): Payment
{
    $payment = khoanP3($test);
    if ($extra) {
        $second = khoanP3($test, $payment->order);
    }
    MH::xacNhan($payment);
    if ($extra) {
        MH::xacNhan($second);
    }
    $order = $payment->order()->firstOrFail();
    $run = app(ClaimGrantAttempt::class)->handle($order->id, $order->grant_ticket, $order->grant_job_id);
    app(GrantOrderItem::class)->handle($run);
    DB::table('jobs')->delete();

    return $payment;
}

it('AC-07-83 Quét chốt hoàn tất khi dòng cuối đã cấp', function () {
    $payment = dungTruocChot($this);
    $this->artisan('edunest:recover-granting-orders')->assertSuccessful();
    expect($payment->order()->first()->status)->toBe(DH::HoanTat)->and(MH::soViec())->toBe(0)->and(Enrollment::query()->count())->toBe(1);
});

it('AC-07-84 Quét chốt cần xử lý khi đã cấp nhưng còn khoản cần hoàn', function () {
    $payment = dungTruocChot($this, true);
    $this->artisan('edunest:recover-granting-orders')->assertSuccessful();
    expect($payment->order()->first()->status)->toBe(DH::CanXuLy)->and(MH::soViec())->toBe(0)->and(Enrollment::query()->count())->toBe(1);
});

it('AC-07-86 Xác nhận lặp chốt hoàn tất khi quét chưa chạy', function () {
    $payment = dungTruocChot($this);
    $deadline = Enrollment::query()->sole()->het_han_luc;
    MH::luc('10:05:00');
    MH::xacNhan($payment);
    expect($payment->order()->first()->status)->toBe(DH::HoanTat)->and(MH::soViec())->toBe(0)
        ->and(Enrollment::query()->sole()->het_han_luc->equalTo($deadline))->toBeTrue();
});

it('TV-04 Giữ lựa chọn đầu khi gửi lại trước khi webhook tới', function () {
    $payment = khoanP3($this);
    $this->signIn($this->a)->post(route('gateway.simulated.confirm', $payment), ['result' => 'THAT_BAI'])->assertRedirect();
    $this->post(route('gateway.simulated.confirm', $payment), ['result' => 'THANH_CONG'])->assertRedirect();
    expect(MH::soViec('default'))->toBe(1);
    $this->post(route('gateway.simulated.resend', $payment))->assertRedirect();
    $payloads = DB::table('jobs')->where('queue', 'default')->pluck('payload')
        ->map(fn ($payload) => json_decode($payload, true)['edunest_confirmation']['body']);
    expect($payloads->count())->toBe(2)->and($payloads->unique()->all())->toBe([PaymentConfirmation::simulated($payment, KQ::ThatBai)]);
});
it('AC-07-43 Đặt mua nhiều dòng')->todo('P5: giỏ hàng.');
it('AC-07-44 Cấp lỗi một dòng trong giỏ')->todo('P5: giỏ hàng.');
it('AC-07-45 Thông báo mua thành công')->todo('P5: thông báo.');
it('AC-07-60 Thông báo thanh toán và cấp quyền')->todo('P5: thông báo.');
it('AC-07-76 Hai dòng gia hạn đồng thời')->todo('P5: gia hạn.');
it('AC-07-77 Gia hạn khi quyền bị thu hồi')->todo('P5: gia hạn.');
it('AC-07-78 Mua ngay tranh chấp thanh toán giỏ')->todo('P5: giỏ hàng.');
it('AC-07-79 Chụp giá khuyến mãi theo ngày')->todo('P5: khuyến mãi.');
it('AC-07-80 Áp mã và khởi tạo khoản')->todo('P5: mã giảm giá.');
it('AC-07-85 Phục hồi việc cấp lỗi của đơn nhiều dòng')->todo('P5: giỏ hàng.');
it('AC-07-87 Phục hồi khi gián đoạn giữa các dòng')->todo('P5: giỏ hàng.');
it('AC-07-88 Khoản sai tiền giữ lượt mã')->todo('P5: mã giảm giá.');
it('AC-07-89 Hủy đơn giỏ trả lượt mã một lần')->todo('P5: giỏ hàng và mã giảm giá.');
it('AC-07-90 Ghi danh từ mã có trước lượt cấp')->todo('P5: mã kích hoạt và Chọn hoàn.');
it('AC-07-91 Quyền xem hóa đơn')->todo('P5: hóa đơn.');
it('AC-07-92 Phát hành hóa đơn đồng thời')->todo('P5: hóa đơn.');
it('AC-07-93 Biên hóa đơn sau đối soát')->todo('P5: hóa đơn.');
it('AC-07-94 Đối soát khoản chuyển ngân hàng')->todo('P8: chuyển khoản.');
it('AC-07-95 Xử lý khoản chuyển ngân hàng')->todo('P8: chuyển khoản.');
it('AC-07-96 Gửi lặp xác nhận chuyển ngân hàng')->todo('P8: chuyển khoản.');
