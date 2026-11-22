<?php

use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M07\MarkPaymentRefunded;
use App\Actions\M07\StopGrantAndRefund;
use App\Actions\M08\PlaceRenewalOrder;
use App\Actions\M08\RevokeEnrollment;
use App\Actions\M12\ApplyCoupon;
use App\Enums\M07\OrderCancelReason;
use App\Enums\M07\OrderItemKind;
use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentReconcileStatus;
use App\Enums\M08\EnrollmentValidity;
use App\Enums\M10\AttemptCloseReason;
use App\Enums\M10\AttemptStatus;
use App\Models\M07\Order;
use App\Models\M08\Enrollment;
use App\Support\DomainRejection;
use App\Support\M08\EnrollmentValidityChecker;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;
use Tests\Support\MuaHang as MH;
use Tests\Support\ThuongMai as TM;

pest()->group('P5');

beforeEach(function () {
    $this->d = TM::d13();
    $this->a = $this->d['a'];
    $this->c = $this->d['lan'];
    $this->k1 = $this->d['k1'];
    $this->k1->forceFill(['extension_price' => 199000])->save();
});

afterEach(fn () => $this->travelBack());

function giaHanP5(object $t, Enrollment $e): Order
{
    $order = app(PlaceRenewalOrder::class)->handle($t->a, $e);
    MH::xacNhan(MH::khoiTao($t->a, $order));
    MH::chayCap();

    return $order->fresh();
}

it('AC-15-19 AC-15-27 Gia hạn khi còn hạn: hạn mới = hạn cũ + số tháng; đơn một dòng GIA_HAN không áp mã', function () {
    $e = MH::ghiDanh($this->a, $this->k1, ['het_han_luc' => now()->addDays(7)]);
    $old = $e->het_han_luc;
    $order = app(PlaceRenewalOrder::class)->handle($this->a, $e);
    $item = $order->items()->sole();
    expect($item->kind)->toBe(OrderItemKind::GiaHan)->and($order->total)->toBe(199000)->and($item->access_months)->toBe(12);
    TM::ma($this->c);
    expect(fn () => app(ApplyCoupon::class)->handle($this->a, $order, 'HOA12'))->toThrow(DomainRejection::class, 'gia hạn');
    MH::xacNhan(MH::khoiTao($this->a, $order));
    MH::chayCap();
    expect($order->fresh()->status)->toBe(OrderStatus::HoanTat)
        ->and($e->fresh()->het_han_luc->equalTo($old->addMonthsNoOverflow(12)))->toBeTrue();
});

it('AC-15-21 Gia hạn khi đã hết hạn: hạn mới = lúc cấp + số tháng', function () {
    $e = MH::ghiDanh($this->a, $this->k1, ['het_han_luc' => now()->subDays(10)]);
    giaHanP5($this, $e);
    expect($e->fresh()->het_han_luc->equalTo(now()->addMonthsNoOverflow(12)))->toBeTrue()
        ->and(EnrollmentValidityChecker::for($e->fresh()))->toBe(EnrollmentValidity::ConHan);
});

it('AC-15-24 AC-15-56 DT-40 Hai đơn gia hạn cộng đủ hai lần; cộng tháng theo lịch không tràn', function () {
    MH::luc('2026-01-31 10:00:00');
    $this->k1->forceFill(['access_months' => 1])->save();
    $e = MH::ghiDanh($this->a, $this->k1, ['het_han_luc' => now()->addHours(2)]);
    $o1 = app(PlaceRenewalOrder::class)->handle($this->a, $e);
    $o2 = app(PlaceRenewalOrder::class)->handle($this->a, $e);
    MH::xacNhan(MH::khoiTao($this->a, $o1));
    MH::xacNhan(MH::khoiTao($this->a, $o2));
    MH::chayCap();
    MH::chayCap();
    expect($e->fresh()->het_han_luc->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d'))->toBe('2026-03-28');
});

it('AC-15-28 AC-15-29 AC-15-31 A4 Không gia hạn ghi danh vĩnh viễn, bị thu hồi, hoặc khóa chưa bán gia hạn', function () {
    $e = MH::ghiDanh($this->a, $this->k1, ['het_han_luc' => null]);
    expect(fn () => app(PlaceRenewalOrder::class)->handle($this->a, $e))->toThrow(DomainRejection::class, 'vĩnh viễn');
    $e->update(['het_han_luc' => now()->addDay(), 'revoked_at' => now()]);
    expect(fn () => app(PlaceRenewalOrder::class)->handle($this->a, $e))->toThrow(DomainRejection::class, 'thu hồi');
    $e->update(['revoked_at' => null]);
    $this->k1->forceFill(['extension_price' => null])->save();
    expect(fn () => app(PlaceRenewalOrder::class)->handle($this->a, $e))->toThrow(DomainRejection::class, 'chưa mở bán gia hạn');
    $this->signIn($this->d['b'])->post(route('student.enrollments.renew', $e))->assertForbidden();
});

it('AC-15-47 A11 Khóa ngừng bán vẫn gia hạn được', function () {
    $e = MH::ghiDanh($this->a, $this->k1, ['het_han_luc' => now()->addDay()]);
    app(ChangeSaleStatus::class)->stop($this->c, $this->k1->fresh());
    expect(giaHanP5($this, $e)->status)->toBe(OrderStatus::HoanTat);
});

it('AC-15-46 AC-15-60 DT-41 A10 Thu hồi trước khi cấp dòng GIA_HAN: dòng CAP_LOI, không cộng hạn; chọn hoàn rồi hủy sau hoàn (CR-133)', function () {
    $e = MH::ghiDanh($this->a, $this->k1, ['het_han_luc' => now()->addDay()]);
    $before = $e->het_han_luc;
    $order = app(PlaceRenewalOrder::class)->handle($this->a, $e);
    $payment = MH::khoiTao($this->a, $order);
    MH::xacNhan($payment);
    app(RevokeEnrollment::class)->revoke($this->c, $e, 'Chia sẻ tài khoản', true);
    MH::chayCap();
    $order->refresh();
    expect($order->items()->sole()->status)->toBe(OrderItemStatus::CapLoi)->and($order->status)->toBe(OrderStatus::CanXuLy)
        ->and($e->fresh()->het_han_luc->equalTo($before))->toBeTrue();
    app(StopGrantAndRefund::class)->handle($this->c, $order, $payment, 'Không khôi phục', true);
    expect($payment->fresh()->reconcile_status)->toBe(PaymentReconcileStatus::CanHoan);
    app(MarkPaymentRefunded::class)->handle($this->c, $order, $payment->fresh(), 'Đã chuyển trả', true);
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::DaHuy)->and($order->cancel_reason)->toBe(OrderCancelReason::HuySauHoan);
});

it('AC-15-33 AC-15-34 AC-15-38 E22 Thu hồi chốt lượt đang làm lý do THU_HOI; khôi phục giữ hạn', function () {
    $this->travelTo(HocTap::at('09:00:00'));
    Storage::fake('local');
    $h = new HocTap;
    $h->enrollment->update(['het_han_luc' => now()->addMonth()]);
    $attempt = $h->start();
    $hl = $h->enrollment->fresh()->het_han_luc;
    app(RevokeEnrollment::class)->revoke($h->g, $h->enrollment, 'Vi phạm điều khoản', true);
    expect($attempt->fresh()->status)->toBe(AttemptStatus::DaNop)->and($attempt->fresh()->close_reason)->toBe(AttemptCloseReason::ThuHoi)
        ->and(EnrollmentValidityChecker::for($h->enrollment->fresh()))->toBe(EnrollmentValidity::DaThuHoi);
    $this->signIn($h->a)->get($h->lessonUrl())->assertForbidden();
    expect(fn () => app(RevokeEnrollment::class)->revoke($h->g, $h->enrollment, 'lần hai', true))->toThrow(DomainRejection::class);
    app(RevokeEnrollment::class)->restore($h->g, $h->enrollment);
    expect($h->enrollment->fresh()->het_han_luc->equalTo($hl))->toBeTrue()
        ->and(EnrollmentValidityChecker::for($h->enrollment->fresh()))->toBe(EnrollmentValidity::ConHan)
        ->and($attempt->fresh()->status)->toBe(AttemptStatus::DaNop);
});

it('AC-15-36 AC-15-41 A8 Thu hồi cần lý do và xác nhận; học viên không thu hồi được', function () {
    $e = MH::ghiDanh($this->a, $this->k1, ['het_han_luc' => now()->addDay()]);
    expect(fn () => app(RevokeEnrollment::class)->revoke($this->c, $e, '  ', true))->toThrow(DomainRejection::class)
        ->and(fn () => app(RevokeEnrollment::class)->revoke($this->c, $e, 'Lý do', false))->toThrow(DomainRejection::class);
    $this->signIn($this->a)->post(route('academic.enrollments.revoke', $e), ['reason' => 'x', 'confirmed' => 1])->assertForbidden();
    expect($e->fresh()->revoked_at)->toBeNull();
});

it('AC-15-39 A9 Khôi phục khi hạn đã qua: hiệu lực hết hạn, hạn giữ nguyên', function () {
    $e = MH::ghiDanh($this->a, $this->k1, ['het_han_luc' => now()->addHour()]);
    app(RevokeEnrollment::class)->revoke($this->c, $e, 'Tạm khóa', true);
    $this->travel(2)->hours();
    app(RevokeEnrollment::class)->restore($this->c, $e);
    expect(EnrollmentValidityChecker::for($e->fresh()))->toBe(EnrollmentValidity::HetHan);
});
