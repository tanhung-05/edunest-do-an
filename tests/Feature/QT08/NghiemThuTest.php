<?php

use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M07\AcceptLatePayment;
use App\Actions\M07\CancelOrder;
use App\Actions\M07\ExpireOrder;
use App\Actions\M07\MarkPaymentRefunded;
use App\Actions\M07\RecoverOrderProcessing;
use App\Actions\M08\RegrantOrderItem;
use App\Enums\M01\RoleCode;
use App\Enums\M07\OrderItemStatus as DD;
use App\Enums\M07\OrderStatus as DH;
use App\Enums\M07\PaymentReconcileStatus as DS;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use App\Models\M08\Enrollment;
use App\Models\M11\AuditLog;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\Tx;
use Tests\Support\MuaHang as MH;

pest()->group('P3');

beforeEach(function () {
    $this->d = MH::d7();
    $this->a = $this->d['a'];
    $this->b = $this->d['b'];
    $this->c = $this->d['lan'];
    $this->k = $this->d['k1'];
    MH::luc('10:00:00');
});

afterEach(fn () => test()->travelBack());

function donXuLy(object $test, string $case): array
{
    $order = MH::dat($test->a, $test->k);
    $first = MH::khoiTao($test->a, $order);
    $second = str_contains($case, 'hai') ? MH::khoiTao($test->a, $order) : null;

    if (str_contains($case, 'muon')) {
        if (str_contains($case, 'huy')) {
            app(CancelOrder::class)->handle($test->a, $order);
        }
        MH::luc('10:45:00');
        MH::xacNhan($first);
        if ($second !== null) {
            MH::xacNhan($second);
        }
    } elseif (str_contains($case, 'sai')) {
        MH::xacNhan($first, 400000);
    } else {
        MH::xacNhan($first);
        if (str_contains($case, 'loi')) {
            MH::dong($order)->update(['status' => DD::CapLoi, 'grant_attempts' => 4]);
            app(RecoverOrderProcessing::class)->handle($order->id);
        } elseif ($case !== 'cap') {
            MH::chayCap();
        }
        if ($second !== null) {
            MH::xacNhan($second);
        }
    }

    return [$order->fresh(), $first->fresh(), $second?->fresh()];
}

function chapNhan(object $test, Order $order, Payment $payment): Payment
{
    return app(AcceptLatePayment::class)->handle($test->c, $order, $payment);
}

function hoan(object $test, Order $order, Payment $payment, string $why = 'Đã hoàn qua cổng', bool $confirmed = true): Payment
{
    return app(MarkPaymentRefunded::class)->handle($test->c, $order, $payment, $why, $confirmed);
}

function capLai(object $test, Order $order)
{
    return app(RegrantOrderItem::class)->handle($test->c, $order, MH::dong($order));
}

function lichSu(Order $order): array
{
    return test()->get(route('academic.orders.show', $order))->assertOk()->viewData('page')['props'];
}

it('AC-08-01 Cấp lại tạo ghi danh và chốt hoàn tất kèm audit', function () {
    [$order] = donXuLy($this, 'loi');
    MH::luc('15:30:00');
    $this->signIn($this->c)->post(route('academic.orders.items.regrant', [$order, MH::dong($order)]))->assertRedirect();
    $enrollment = Enrollment::query()->sole();
    expect(MH::dong($order)->status)->toBe(DD::DaCap)->and($order->fresh()->status)->toBe(DH::HoanTat)
        ->and($enrollment->course_version_id)->toBe($this->k->current_version_id)
        ->and($enrollment->het_han_luc->equalTo(now()->addYear()))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'ORDER_ITEM_REGRANT')->sole()->actor_id)->toBe($this->c->id);
});

it('AC-08-02 Hàng đơn cần xử lý chỉ chứa đúng trạng thái', function () {
    [$order] = donXuLy($this, 'loi');
    MH::dat($this->a, $this->d['k2']);
    MH::dat($this->b, $this->k)->update(['status' => DH::HetHan]);
    $this->signIn($this->c);
    $props = $this->get(route('academic.orders.index'))->assertOk()->viewData('page')['props'];
    expect(array_column($props['needsAttention']['data'], 'code'))->toBe([$order->public_code]);
});

it('AC-08-03 Hoàn khoản thu trùng chốt đơn, giữ hạn đã cấp', function () {
    [$order, , $extra] = donXuLy($this, 'xong-hai');
    $deadline = Enrollment::query()->sole()->het_han_luc;
    hoan($this, $order, $extra);
    expect($extra->fresh()->reconcile_status)->toBe(DS::DaHoanLai)->and($order->fresh()->status)->toBe(DH::HoanTat)
        ->and(Enrollment::query()->sole()->het_han_luc->equalTo($deadline))->toBeTrue();
});

it('AC-08-04 Hoàn xong khoản trùng vẫn chờ cấp lại dòng lỗi', function () {
    [$order, , $extra] = donXuLy($this, 'loi-hai');
    hoan($this, $order, $extra);
    expect($order->fresh()->status)->toBe(DH::CanXuLy);
    capLai($this, $order);
    expect($order->fresh()->status)->toBe(DH::HoanTat);
});

it('AC-08-05 Chấp nhận khoản muộn cấp theo bản công bố mới', function () {
    [$order, $payment] = donXuLy($this, 'muon');
    $version = MH::congBoMoi($this->k, $this->d['t1'], $this->d['minh'], $this->c);
    $this->signIn($this->c)->post(route('academic.orders.payments.accept-late', [$order, $payment]))->assertRedirect();
    expect($payment->fresh()->reconcile_status)->toBe(DS::HopLe)->and($order->fresh()->status)->toBe(DH::DangCapQuyen);
    MH::luc('14:00:10');
    MH::chayCap();
    expect($order->fresh()->status)->toBe(DH::HoanTat)->and(Enrollment::query()->sole()->course_version_id)->toBe($version->id);
});

it('AC-08-06 Chấp nhận khoản muộn của đơn đã hủy', function () {
    [$order, $payment] = donXuLy($this, 'muon-huy');
    chapNhan($this, $order, $payment);
    MH::chayCap();
    expect($payment->fresh()->reconcile_status)->toBe(DS::HopLe)->and($order->fresh()->status)->toBe(DH::HoanTat)
        ->and(Enrollment::query()->count())->toBe(1);
});

it('AC-08-07 Hoàn khoản muộn cuối đưa đơn về hết hạn', function () {
    [$order, $payment] = donXuLy($this, 'muon');
    hoan($this, $order, $payment);
    expect($payment->fresh()->reconcile_status)->toBe(DS::DaHoanLai)->and($order->fresh()->status)->toBe(DH::HetHan)
        ->and(MH::dong($order)->status)->toBe(DD::ChuaCap);
});

it('AC-08-08 Hoàn khoản muộn cuối đưa đơn về đã hủy', function () {
    [$order, $payment] = donXuLy($this, 'muon-huy');
    hoan($this, $order, $payment);
    expect($payment->fresh()->reconcile_status)->toBe(DS::DaHoanLai)->and($order->fresh()->status)->toBe(DH::DaHuy);
});

it('AC-08-09 Cấp khoản muộn rồi hoàn khoản còn lại mới chốt', function () {
    [$order, $payment, $extra] = donXuLy($this, 'muon-hai');
    chapNhan($this, $order, $payment);
    MH::chayCap();
    expect($order->fresh()->status)->toBe(DH::CanXuLy)->and($extra->fresh()->reconcile_status)->toBe(DS::CanHoan);
    hoan($this, $order, $extra);
    expect($order->fresh()->status)->toBe(DH::HoanTat)->and(Enrollment::query()->count())->toBe(1);
});

it('AC-08-10 Hoàn từng khoản muộn, khoản cuối mới về đã hủy', function () {
    [$order, $payment, $extra] = donXuLy($this, 'muon-huy-hai');
    hoan($this, $order, $payment);
    expect($order->fresh()->status)->toBe(DH::CanXuLy);
    hoan($this, $order, $extra);
    expect($order->fresh()->status)->toBe(DH::DaHuy);
});

it('AC-08-11 Không chấp nhận khoản muộn thứ hai', function () {
    [$order, $payment, $extra] = donXuLy($this, 'muon-hai');
    chapNhan($this, $order, $payment);
    MH::chayCap();
    expect(fn () => chapNhan($this, $order, $extra))->toThrow(DomainRejection::class);
    expect($extra->fresh()->reconcile_status)->toBe(DS::CanHoan)->and(Enrollment::query()->count())->toBe(1);
});

it('AC-08-12 Có ghi danh thì từ chối khoản muộn và vẫn hoàn được', function () {
    [$order, $payment] = donXuLy($this, 'muon');
    $existing = MH::ghiDanh($this->a, $this->k, ['het_han_luc' => now()->addMonths(5)]);
    expect(fn () => chapNhan($this, $order, $payment))->toThrow(DomainRejection::class);
    hoan($this, $order, $payment);
    expect($order->fresh()->status)->toBe(DH::HetHan)->and($existing->fresh()->het_han_luc->equalTo($existing->het_han_luc))->toBeTrue();
});

it('AC-08-13 Khóa ngừng bán vẫn cấp được sau chấp nhận khoản muộn', function () {
    [$order, $payment] = donXuLy($this, 'muon');
    app(ChangeSaleStatus::class)->stop($this->c, $this->k);
    chapNhan($this, $order, $payment);
    MH::chayCap();
    expect($order->fresh()->status)->toBe(DH::HoanTat)->and(Enrollment::query()->count())->toBe(1);
});

it('AC-08-14 Giả lập lỗi khi cấp lại giữ dòng lỗi và ghi audit', function () {
    [$order] = donXuLy($this, 'loi');
    MH::giaLapLoi(true);
    $this->signIn($this->c)->post(route('academic.orders.items.regrant', [$order, MH::dong($order)]))->assertSessionHasErrors('general');
    expect(MH::dong($order)->status)->toBe(DD::CapLoi)->and($order->fresh()->status)->toBe(DH::CanXuLy)
        ->and(Enrollment::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'ORDER_ITEM_REGRANT')->count())->toBe(1);
});

it('AC-08-15 Cấp lại dùng khoản gốc, không thu lần hai', function () {
    [$order, $payment] = donXuLy($this, 'loi');
    capLai($this, $order);
    expect($order->payments()->sole()->id)->toBe($payment->id)->and($payment->fresh()->received_amount)->toBe(499000);
});

it('AC-08-16 Không nhập lý do thì không hoàn hoặc ghi audit', function () {
    [$order, , $payment] = donXuLy($this, 'xong-hai');
    $this->signIn($this->c)->postJson(route('academic.orders.payments.mark-refunded', [$order, $payment]), ['confirmed' => 1])->assertUnprocessable()->assertJsonValidationErrors('reason');
    expect($payment->fresh()->reconcile_status)->toBe(DS::CanHoan)->and(AuditLog::query()->where('action', 'PAYMENT_MARK_REFUNDED')->count())->toBe(0);
});

it('AC-08-17 Từ chối toàn khoảng trắng, chấp nhận lý do một ký tự', function () {
    [$order, , $payment] = donXuLy($this, 'xong-hai');
    expect(fn () => hoan($this, $order, $payment, '   '))->toThrow(DomainRejection::class);
    hoan($this, $order, $payment, 'x');
    expect($payment->fresh()->refund_reason)->toBe('x');
});

it('AC-08-18 Không hoàn trực tiếp khoản hợp lệ', function () {
    [$order, $payment] = donXuLy($this, 'xong');
    expect(fn () => hoan($this, $order, $payment))->toThrow(DomainRejection::class);
    expect($payment->fresh()->reconcile_status)->toBe(DS::HopLe)->and(Enrollment::query()->count())->toBe(1);
});

it('AC-08-19 Lặp thao tác hoàn chỉ ghi audit một lần', function () {
    [$order, , $payment] = donXuLy($this, 'xong-hai');
    hoan($this, $order, $payment);
    expect(fn () => hoan($this, $order, $payment))->toThrow(DomainRejection::class);
    expect(AuditLog::query()->where('action', 'PAYMENT_MARK_REFUNDED')->count())->toBe(1);
});

it('AC-08-20 Cấp lại lặp không cấp thêm ghi danh', function () {
    [$order] = donXuLy($this, 'loi');
    capLai($this, $order);
    expect(fn () => capLai($this, $order))->toThrow(DomainRejection::class);
    expect(Enrollment::query()->count())->toBe(1)->and($order->fresh()->status)->toBe(DH::HoanTat);
});

it('AC-08-21 Từ chối cấp lại dòng chưa cấp và đã cấp', function () {
    [$order] = donXuLy($this, 'cap');
    expect(fn () => capLai($this, $order))->toThrow(DomainRejection::class);
    MH::chayCap();
    expect(fn () => capLai($this, $order))->toThrow(DomainRejection::class);
    expect(MH::dong($order)->status)->toBe(DD::DaCap);
});

it('AC-08-22 Khoản thu trùng chỉ hoàn, không cấp quyền', function () {
    [$order, , $payment] = donXuLy($this, 'xong-hai');
    $this->signIn($this->c);
    $props = lichSu($order);
    expect($props['order']['payments'][1]['can_accept'])->toBeFalse()->and($props['order']['payments'][1]['can_refund'])->toBeTrue();
    expect(fn () => chapNhan($this, $order, $payment))->toThrow(DomainRejection::class);
});

it('AC-08-23 Vai ngoài Giáo vụ không cấp lại được', function () {
    [$order] = donXuLy($this, 'loi');
    foreach ([$this->a, $this->d['t1'], taiKhoan('quantri@edunest.test', RoleCode::QuanTri)] as $user) {
        $this->signIn($user)->postJson(route('academic.orders.items.regrant', [$order, MH::dong($order)]))->assertForbidden();
    }
    expect(MH::dong($order)->status)->toBe(DD::CapLoi);
});

it('AC-08-24 Vai ngoài Giáo vụ không hoàn khoản được', function () {
    [$order, , $payment] = donXuLy($this, 'xong-hai');
    foreach ([$this->a, $this->d['t1'], taiKhoan('quantri@edunest.test', RoleCode::QuanTri)] as $user) {
        $this->signIn($user)->postJson(route('academic.orders.payments.mark-refunded', [$order, $payment]), ['reason' => 'x', 'confirmed' => 1])->assertForbidden();
    }
    expect($payment->fresh()->reconcile_status)->toBe(DS::CanHoan);
});

it('AC-08-25 Vai ngoài Giáo vụ không chấp nhận khoản muộn', function () {
    [$order, $payment] = donXuLy($this, 'muon');
    foreach ([$this->a, $this->d['t1'], taiKhoan('quantri@edunest.test', RoleCode::QuanTri)] as $user) {
        $this->signIn($user)->postJson(route('academic.orders.payments.accept-late', [$order, $payment]))->assertForbidden();
    }
    expect($payment->fresh()->reconcile_status)->toBe(DS::CanHoan);
});

it('AC-08-26 Chỉ ghi hoàn sau khi xác nhận', function () {
    [$order, , $payment] = donXuLy($this, 'xong-hai');
    expect(fn () => hoan($this, $order, $payment, 'x', false))->toThrow(DomainRejection::class);
    expect($payment->fresh()->reconcile_status)->toBe(DS::CanHoan);
    hoan($this, $order, $payment);
    expect($payment->fresh()->reconcile_status)->toBe(DS::DaHoanLai);
});

it('AC-08-27 Audit ghi người làm, giờ và giá trị cũ mới', function () {
    [$order, , $extra] = donXuLy($this, 'loi-hai');
    capLai($this, $order);
    hoan($this, $order, $extra);
    MH::luc('10:00:00');
    $this->a = $this->b;
    [$lateOrder, $late] = donXuLy($this, 'muon');
    chapNhan($this, $lateOrder, $late);
    $logs = AuditLog::query()->where('actor_id', $this->c->id)
        ->whereIn('action', ['ORDER_ITEM_REGRANT', 'PAYMENT_MARK_REFUNDED', 'PAYMENT_ACCEPT_LATE'])->orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['ORDER_ITEM_REGRANT', 'PAYMENT_MARK_REFUNDED', 'PAYMENT_ACCEPT_LATE'])
        ->and($logs[0]->old_values['status'])->toBe('CAP_LOI')
        ->and($logs[0]->new_values['status'])->toBe('DA_CAP')
        ->and($logs[1]->new_values['reconcile_status'])->toBe('DA_HOAN_LAI')
        ->and($logs[2]->new_values['order_status'])->toBe('DANG_CAP_QUYEN');
});

it('AC-08-30 Hoàn sai tiền khi chờ hoặc hết hạn giữ trạng thái đơn', function (bool $expired) {
    [$order, $payment] = donXuLy($this, 'sai');
    if ($expired) {
        MH::luc('10:40:00');
        app(ExpireOrder::class)->handle($order->id);
    }
    $this->signIn($this->c);
    $url = route('academic.orders.index');
    expect(array_column($this->get($url)->assertOk()->viewData('page')['props']['refunds']['data'], 'id'))->toBe([$payment->id]);
    hoan($this, $order, $payment);
    expect($this->get($url)->assertOk()->viewData('page')['props']['refunds']['data'])->toBe([])
        ->and($order->fresh()->status)->toBe($expired ? DH::HetHan : DH::ChoThanhToan);
})->with([true, false]);

it('AC-08-31 Khoản muộn sai tiền chỉ được hoàn', function () {
    $order = MH::dat($this->a, $this->k);
    $payment = MH::khoiTao($this->a, $order);
    app(ChangeSaleStatus::class)->stop($this->c, $this->k);
    MH::luc('10:45:00');
    MH::xacNhan($payment, 400000);
    expect(fn () => chapNhan($this, $order, $payment))->toThrow(DomainRejection::class);
    hoan($this, $order, $payment);
    expect($payment->fresh()->reconcile_status)->toBe(DS::DaHoanLai)->and($order->fresh()->status)->toBe(DH::HetHan);
});

it('AC-08-32 Khoản muộn cấp lỗi hết ngân sách rồi Giáo vụ cấp lại', function () {
    [$order, $payment] = donXuLy($this, 'muon');
    MH::luc('14:00:10');
    chapNhan($this, $order, $payment);
    MH::giaLapLoi(true);
    foreach (['14:00:10', '14:01:10', '14:06:10', '14:21:10'] as $time) {
        MH::luc($time);
        expect(MH::chayCap())->toBeTrue();
    }
    expect($order->fresh()->status)->toBe(DH::CanXuLy)->and(MH::dong($order)->status)->toBe(DD::CapLoi);
    MH::giaLapLoi(false);
    MH::luc('15:30:00');
    capLai($this, $order);
    expect($order->fresh()->status)->toBe(DH::HoanTat)->and(Enrollment::query()->sole()->het_han_luc->equalTo(now()->addYear()))->toBeTrue();
});

it('AC-08-35 Hàng việc và chi tiết đơn chỉ dành cho Giáo vụ', function () {
    [$order] = donXuLy($this, 'loi');
    foreach ([$this->a, $this->d['t1'], taiKhoan('quantri@edunest.test', RoleCode::QuanTri)] as $user) {
        $this->signIn($user)->get(route('academic.orders.index'))->assertForbidden();
        $this->get(route('academic.orders.show', $order))->assertForbidden();
    }
});

it('AC-08-38 Hàng khoản cần hoàn gồm khoản sai tiền trên đơn chờ', function () {
    [$needs, , $duplicate] = donXuLy($this, 'xong-hai');
    $this->k = $this->d['k2'];
    [, $wrong] = donXuLy($this, 'sai');
    $this->signIn($this->c);
    $props = $this->get(route('academic.orders.index'))->assertOk()->viewData('page')['props'];
    expect(array_column($props['refunds']['data'], 'id'))->toBe([$wrong->id, $duplicate->id])
        ->and(array_column($props['needsAttention']['data'], 'code'))->toBe([$needs->public_code]);
});

it('AC-08-39 Hoàn khoản sai tiền cũ sau khi thanh toán lại và cấp quyền', function () {
    [$order, $wrong] = donXuLy($this, 'sai');
    MH::xacNhan(MH::khoiTao($this->a, $order));
    MH::chayCap();
    $deadline = Enrollment::query()->sole()->het_han_luc;
    hoan($this, $order, $wrong);
    expect($order->fresh()->status)->toBe(DH::HoanTat)->and(Enrollment::query()->sole()->het_han_luc->equalTo($deadline))->toBeTrue();
});

it('AC-08-40 Hoàn khoản muộn thứ hai khi đang cấp chưa chốt đơn', function () {
    [$order, $payment, $extra] = donXuLy($this, 'muon-hai');
    chapNhan($this, $order, $payment);
    hoan($this, $order, $extra);
    expect($order->fresh()->status)->toBe(DH::DangCapQuyen);
    MH::chayCap();
    expect($order->fresh()->status)->toBe(DH::HoanTat);
});

it('AC-08-41 Hoàn khoản sai tiền giữ đơn đã hủy', function () {
    [$order, $wrong] = donXuLy($this, 'sai');
    app(CancelOrder::class)->handle($this->a, $order);
    hoan($this, $order, $wrong);
    expect($order->fresh()->status)->toBe(DH::DaHuy)->and($wrong->fresh()->reconcile_status)->toBe(DS::DaHoanLai)
        ->and(Enrollment::query()->count())->toBe(0);
});

it('AC-08-42 Chi tiết phân biệt sai tiền, thu trùng và khoản muộn đủ điều kiện', function () {
    [$wrongOrder] = donXuLy($this, 'sai');
    $this->k = $this->d['k2'];
    [$duplicateOrder] = donXuLy($this, 'xong-hai');
    $this->k = $this->d['k3'];
    [$lateOrder] = donXuLy($this, 'muon');
    $this->signIn($this->c);
    foreach ([[$wrongOrder, 0, 'Sai số tiền', false], [$duplicateOrder, 1, 'Thu trùng', false], [$lateOrder, 0, 'Thu muộn', true]] as [$order, $i, $reason, $accept]) {
        $payment = lichSu($order)['order']['payments'][$i];
        expect($payment['reason'])->toBe($reason)->and($payment['can_accept'])->toBe($accept)->and($payment['can_refund'])->toBeTrue();
    }
});

function saiTienVaMuon(object $test): array
{
    [$order, $wrong] = donXuLy($test, 'sai');
    $late = MH::khoiTao($test->a, $order);
    MH::luc('10:45:00');
    MH::xacNhan($late);

    return [$order, $wrong, $late];
}

it('AC-08-43 Hoàn hết khoản muộn trả trạng thái cũ dù còn khoản sai tiền', function () {
    [$order, $wrong, $late] = saiTienVaMuon($this);
    hoan($this, $order, $late);
    expect($order->fresh()->status)->toBe(DH::HetHan)->and($wrong->fresh()->reconcile_status)->toBe(DS::CanHoan);
});

it('AC-08-44 Cấp khoản muộn rồi hoàn cả khoản sai tiền cũ mới hoàn tất', function () {
    [$order, $wrong, $late] = saiTienVaMuon($this);
    chapNhan($this, $order, $late);
    MH::chayCap();
    expect($order->fresh()->status)->toBe(DH::CanXuLy)->and($wrong->fresh()->reconcile_status)->toBe(DS::CanHoan);
    hoan($this, $order, $wrong);
    expect($order->fresh()->status)->toBe(DH::HoanTat);
});

it('AC-08-45 Chấp nhận và hoàn cùng khoản chỉ thao tác đầu thành công', function (bool $acceptFirst) {
    [$order, $payment] = donXuLy($this, 'muon');
    $accept = fn () => chapNhan($this, $order, $payment);
    $refund = fn () => hoan($this, $order, $payment);
    [$first, $second] = $acceptFirst ? [$accept, $refund] : [$refund, $accept];
    $first();
    expect($second)->toThrow(DomainRejection::class);
    MH::chayCap();
    expect($order->fresh()->status)->toBe($acceptFirst ? DH::HoanTat : DH::HetHan)
        ->and($payment->fresh()->reconcile_status)->toBe($acceptFirst ? DS::HopLe : DS::DaHoanLai);
})->with([true, false]);

it('AC-08-46 Hai khoản muộn chỉ chấp nhận khoản ghi trước', function (bool $swap) {
    [$order, $a, $b] = donXuLy($this, 'muon-hai');
    [$first, $second] = $swap ? [$b, $a] : [$a, $b];
    chapNhan($this, $order, $first);
    expect(fn () => chapNhan($this, $order, $second))->toThrow(DomainRejection::class);
    MH::chayCap();
    expect($first->fresh()->reconcile_status)->toBe(DS::HopLe)->and($second->fresh()->reconcile_status)->toBe(DS::CanHoan)
        ->and(Enrollment::query()->count())->toBe(1);
})->with([true, false]);

it('AC-08-47 Hoàn khoản muộn cuối vẫn cần cấp lại nếu dòng lỗi', function () {
    [$order, $payment, $extra] = donXuLy($this, 'muon-hai');
    chapNhan($this, $order, $payment);
    MH::dong($order)->update(['status' => DD::CapLoi, 'grant_attempts' => 4]);
    app(RecoverOrderProcessing::class)->handle($order->id);
    hoan($this, $order, $extra);
    expect($order->fresh()->status)->toBe(DH::CanXuLy);
    capLai($this, $order);
    expect($order->fresh()->status)->toBe(DH::HoanTat);
});

it('AC-08-63 CR-185 Đơn khác đang mở chặn chấp nhận khoản muộn', function () {
    $old = MH::dat($this->a, $this->k);
    $payment = MH::khoiTao($this->a, $old);
    MH::luc('10:40:00');
    $new = MH::dat($this->a, $this->k);
    MH::xacNhan($payment);
    expect(fn () => chapNhan($this, $old, $payment))->toThrow(DomainRejection::class, $new->public_code);
    expect($payment->fresh()->reconcile_status)->toBe(DS::CanHoan);
    hoan($this, $old, $payment);
    expect($old->fresh()->status)->toBe(DH::HetHan)->and($new->fresh()->status)->toBe(DH::ChoThanhToan);
});

it('AC-08-64 Nhật ký Giáo vụ giới hạn ở đơn, dòng và khoản liên quan', function () {
    [$order, , $extra] = donXuLy($this, 'loi-hai');
    $other = MH::dat($this->d['b'], $this->k);
    Tx::run(function () use ($order, $other) {
        Audit::log('TRONG_DON', $this->c, $order);
        Audit::log('NGOAI_DON', $this->c, $other);
        Audit::log('NGOAI_DONG', $this->c, MH::dong($other));
    });
    capLai($this, $order);
    hoan($this, $order, $extra);
    $this->signIn($this->c);
    expect(array_column(lichSu($order)['audit'], 'action'))->toBe(['PAYMENT_MARK_REFUNDED', 'ORDER_ITEM_REGRANT', 'TRONG_DON']);
});

it('AC-08-65 Giáo viên không xem lịch sử thao tác của đơn', function () {
    [$order] = donXuLy($this, 'loi');
    $this->signIn($this->d['t1'])->get(route('academic.orders.show', $order))->assertForbidden();
    $this->signIn($this->c)->get(route('admin.settings.edit'))->assertForbidden();
})->todo('Tuyến nhật ký hệ thống thuộc P4; quyền xem lịch sử đơn có ca AC-08-35.');
it('AC-08-28 Cấp lại gia hạn khi còn hạn')->todo('P5: gia hạn.');
it('AC-08-29 Thông báo sau khi cấp lại')->todo('P5: thông báo.');
it('AC-08-33 Chấp nhận muộn đơn giỏ có dòng trùng')->todo('P5: giỏ hàng.');
it('AC-08-34 Khoản muộn sai tiền của đơn giỏ')->todo('P5: giỏ hàng.');
it('AC-08-36 Cấp lại gia hạn khi đã hết hạn')->todo('P5: gia hạn.');
it('AC-08-37 Biên hiệu lực khi cộng hạn')->todo('P5: gia hạn.');
it('AC-08-48 Cấp lại gia hạn sau khi khôi phục ghi danh')->todo('P5: gia hạn.');
it('AC-08-49 Cấp lại gia hạn khi còn thu hồi')->todo('P5: gia hạn.');
it('AC-08-50 Cấp lại và tác vụ gia hạn đồng thời')->todo('P5: gia hạn.');
it('AC-08-51 Chấp nhận muộn lặp khi có lượt mã')->todo('P5: mã giảm giá.');
it('AC-08-52 Chấp nhận khoản muộn tranh chấp áp mã')->todo('P5: mã giảm giá.');
it('AC-08-53 Chọn hoàn và xác nhận lặp')->todo('P5: DS-5/DH-16.');
it('AC-08-54 Chọn hoàn tranh chấp cấp lại')->todo('P5: DS-5/DH-16.');
it('AC-08-55 Quyền và xác nhận Chọn hoàn')->todo('P5: DS-5/DH-16.');
it('AC-08-56 Chọn hoàn đúng nguyên nhân dừng cấp')->todo('P5: DS-5/DH-16.');
it('AC-08-57 Khoản muộn sau khi chọn hoàn')->todo('P5: DS-5/DH-16.');
it('AC-08-58 Chọn hoàn và xác nhận đã hoàn')->todo('P5: DS-5/DH-16.');
it('AC-08-59 Chấp nhận khoản chuyển ngân hàng thứ hai')->todo('P8: chuyển khoản.');
it('AC-08-60 Quyền tra soát bản chuyển khoản')->todo('P8: chuyển khoản.');
it('AC-08-61 Mở hồ sơ tra soát không khớp')->todo('P8: chuyển khoản.');
it('AC-08-62 Chốt lần đầu và phát lại sự kiện')->todo('P8: đối soát hoa hồng.');
