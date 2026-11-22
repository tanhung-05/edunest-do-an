<?php

use App\Actions\M07\CancelOrder;
use App\Actions\M07\MarkPaymentRefunded;
use App\Actions\M07\PlaceOrder;
use App\Actions\M24\AffiliateCommissions;
use App\Actions\M24\Affiliates;
use App\Actions\M24\Bundles;
use App\Enums\M07\OrderSource;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentReconcileStatus;
use App\Enums\M07\PaymentTxnStatus;
use App\Enums\M24\CommissionStatus;
use App\Models\M07\Order;
use App\Models\M24\Affiliate;
use App\Models\M24\AffiliateCommission;
use App\Models\M24\Bundle;
use App\Support\DomainRejection;
use Tests\Support\MuaHang as MH;
use Tests\Support\ThuongMai as TM;

pest()->group('P8');

beforeEach(function () {
    $this->d = TM::d13();
    $this->a = $this->d['a'];
    $this->b = $this->d['b'];
    $this->gv = $this->d['lan'];
    $this->k1 = TM::gia($this->d['k1'], 500000);
    $this->k2 = TM::gia($this->d['k2'], 300000);
    $this->k3 = TM::gia($this->d['k3'], 200000);
});

afterEach(fn () => $this->travelBack());

function cbTao(object $t, int $price = 700000, bool $deduct = true, ?array $courses = null): Bundle
{
    return app(Bundles::class)->save($t->gv, ['title' => 'Combo Hóa', 'description' => null, 'price' => $price, 'deduct_owned' => $deduct,
        'course_ids' => $courses ?? [$t->k1->id, $t->k2->id, $t->k3->id]]);
}

function cbCtv(object $t, int $rateBp = 1000): Affiliate
{
    return app(Affiliates::class)->save($t->gv, ['email' => $t->b->email, 'code' => 'ban-b', 'rate_bp' => $rateBp, 'is_active' => true]);
}

/** Thanh toán mô phỏng tới khi cấp xong mọi dòng. */
function cbTra(object $t, Order $order): Order
{
    $payment = MH::khoiTao($t->a, $order);
    MH::xacNhan($payment);
    while (MH::chayCap()) {
        // chạy hết việc cấp quyền
    }

    return $order->fresh();
}

function cbLoi(callable $fn): ?string
{
    try {
        $fn();
    } catch (DomainRejection $e) {
        return $e->getMessage();
    }

    return null;
}

it('BR-TT-68/85 CR-103 Giáo vụ tạo combo ≥ 2 khóa, giá ≤ tổng giá niêm yết; vai khác bị từ chối', function () {
    expect(cbLoi(fn () => cbTao($this, 700000, true, [$this->k1->id])))->toContain('ít nhất 2')
        ->and(cbLoi(fn () => cbTao($this, 1000001)))->toContain('Giá combo')
        ->and(cbLoi(fn () => app(Bundles::class)->save($this->a, ['title' => 'X', 'price' => 1, 'deduct_owned' => true,
            'course_ids' => [$this->k1->id, $this->k2->id]])))->toContain('Giáo vụ');
    $bundle = cbTao($this);
    expect($bundle->courses()->count())->toBe(3)->and($bundle->accepts_orders)->toBeTrue();
});

it('QT-26 b3–5 CR-96 Đặt combo: một đơn COMBO, mỗi khóa một dòng, phân bổ theo giá lẻ làm tròn xuống và chia đồng dư', function () {
    $bundle = cbTao($this, 700001);
    $order = app(Bundles::class)->place($this->a, $bundle)['order'];
    $lines = $order->items()->orderBy('sort_order')->get();
    expect($order->source)->toBe(OrderSource::Combo)->and($order->total)->toBe(700001)->and($order->subtotal)->toBe(1000000)
        ->and($lines->sum('final_amount'))->toBe(700001)
        ->and($lines->pluck('final_amount')->all())->toBe([350001, 210000, 140000])
        ->and($lines->every(fn ($l) => $l->bundle_id === $bundle->id))->toBeTrue();
});

it('E10 X4 CR-163 Đã sở hữu: trừ giá lẻ khi bật trừ; tắt trừ thì dừng; sở hữu hết thì 0 đơn; tổng ≤ 0đ thì chặn', function () {
    MH::ghiDanh($this->a, $this->k2);
    $bundle = cbTao($this, 700000);
    $order = app(Bundles::class)->place($this->a, $bundle)['order'];
    expect($order->total)->toBe(400000)->and($order->items()->count())->toBe(2);
    app(CancelOrder::class)->handle($this->a, $order);

    $noDeduct = cbTao($this, 700000, false);
    expect(cbLoi(fn () => app(Bundles::class)->place($this->a, $noDeduct)))->toContain('mua lẻ');

    MH::ghiDanh($this->a, $this->k1);
    $cheap = cbTao($this, 600000);
    expect(cbLoi(fn () => app(Bundles::class)->place($this->a, $cheap)))->toContain('không còn dương');

    MH::ghiDanh($this->a, $this->k3);
    expect(app(Bundles::class)->place($this->a, $bundle)['owned_all'])->toBeTrue()
        ->and(Order::query()->where('user_id', $this->a->id)->where('source', OrderSource::Combo)->count())->toBe(1);
});

it('X3 E10 CR-162 Cờ nhận đơn tắt chặn đơn mới; đơn mở trùng khóa được liệt kê, không tạo đơn', function () {
    $bundle = cbTao($this);
    expect(cbLoi(fn () => app(Bundles::class)->setAccepting($this->gv, $bundle, false, false)))->toContain('xác nhận');
    app(Bundles::class)->setAccepting($this->gv, $bundle, false, true);
    expect(cbLoi(fn () => app(Bundles::class)->place($this->a, $bundle->fresh())))->toContain('tạm ngừng');
    app(Bundles::class)->setAccepting($this->gv, $bundle, true, true);

    $single = MH::dat($this->a, $this->k1);
    $result = app(Bundles::class)->place($this->a, $bundle->fresh());
    expect($result['order'])->toBeNull()->and(collect($result['blocked'])->pluck('id')->all())->toBe([$single->id]);
});

it('BR-TT-71..74 CR-95/164 Nguồn giới thiệu: chụp lúc đặt, chặn tự giới thiệu; hoàn tất tạo hoa hồng làm tròn xuống; chi đúng một lần', function () {
    cbCtv($this, 1250);
    $self = app(PlaceOrder::class)->handle($this->b, $this->k3, 'ban-b');
    expect($self->affiliate_id)->toBeNull();

    $order = app(Bundles::class)->place($this->a, cbTao($this, 700003), 'BAN-B')['order'];
    expect($order->affiliate_id)->not->toBeNull();
    $order = cbTra($this, $order);
    expect($order->status)->toBe(OrderStatus::HoanTat)->and($order->affiliate_rate_bp_snapshot)->toBe(1250);
    $commission = AffiliateCommission::query()->where('order_id', $order->id)->sole();
    expect($commission->amount)->toBe(87500)->and($commission->status)->toBe(CommissionStatus::ChoDuyet);

    expect(cbLoi(fn () => app(AffiliateCommissions::class)->pay($this->a, $commission->id)))->not->toBeNull();
    app(AffiliateCommissions::class)->pay($this->gv, $commission->id);
    expect($commission->fresh()->status)->toBe(CommissionStatus::DaChi)
        ->and(cbLoi(fn () => app(AffiliateCommissions::class)->pay($this->gv, $commission->id)))->toContain('chờ duyệt');
    // Chạy lại bước ghi không tạo thêm bản.
    app(AffiliateCommissions::class)->sweep();
    expect(AffiliateCommission::query()->where('order_id', $order->id)->count())->toBe(1);
});

it('A6 CR-95 Hoàn khoản của đơn có giới thiệu hủy hoa hồng (cả khi đã chi); A7 mã sai không gắn nguồn', function () {
    cbCtv($this);
    expect(app(PlaceOrder::class)->handle($this->a, $this->k2, 'khong-co')->affiliate_id)->toBeNull();
    $order = cbTra($this, app(PlaceOrder::class)->handle($this->a, $this->k1, 'ban-b'));
    $commission = AffiliateCommission::query()->where('order_id', $order->id)->sole();
    app(AffiliateCommissions::class)->pay($this->gv, $commission->id);

    $payment = $order->payments()->sole();
    $payment->forceFill(['reconcile_status' => PaymentReconcileStatus::CanHoan])->save();
    app(MarkPaymentRefunded::class)->handle($this->gv, $order, $payment, 'Hoàn theo yêu cầu', true);
    expect($commission->fresh()->status)->toBe(CommissionStatus::Huy);
});

it('CR-165 Đơn đã có khoản hoàn trước lần hoàn tất đầu: 0 bản hoa hồng, ghi không đủ điều kiện', function () {
    cbCtv($this);
    $order = app(PlaceOrder::class)->handle($this->a, $this->k1, 'ban-b');
    $payment = MH::khoiTao($this->a, $order);
    $payment->forceFill(['txn_status' => PaymentTxnStatus::ThanhCong, 'reconcile_status' => PaymentReconcileStatus::DaHoanLai, 'refund_reason' => 'Hoàn trước', 'refunded_at' => now()])->save();
    $order->forceFill(['first_completed_at' => now()])->save();
    AffiliateCommissions::onFirstCompletion($order->fresh());
    expect($order->fresh()->affiliate_commission_eligible)->toBeFalse()
        ->and(AffiliateCommission::query()->where('order_id', $order->id)->exists())->toBeFalse();
});

it('MH combo công khai, đặt combo qua HTTP, trang Giáo vụ và trang cộng tác viên', function () {
    $bundle = cbTao($this);
    cbCtv($this);
    $this->get(route('public.bundles.index'))->assertOk();
    $this->get(route('public.bundles.show', ['slug' => $bundle->slug, 'ref' => 'ban-b']))->assertOk()
        ->assertInertia(fn ($p) => $p->where('ref', 'ban-b'));
    $this->signIn($this->a)->post(route('student.bundles.orders.store', $bundle), ['ref' => 'ban-b'])->assertRedirect();
    expect(Order::query()->where('user_id', $this->a->id)->where('source', OrderSource::Combo)->value('affiliate_id'))->not->toBeNull();
    $this->signIn($this->gv)->get(route('academic.bundles.index'))->assertOk();
    $this->signIn($this->gv)->get(route('academic.affiliates.index'))->assertOk();
    $this->signIn($this->b)->get(route('student.affiliate.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->where('auth.user.is_affiliate', true));
    $this->signIn($this->a)->get(route('student.affiliate.index'))->assertNotFound();
    $this->get(route('student.dashboard'))->assertInertia(fn ($p) => $p->where('auth.user.is_affiliate', false));
    $this->signIn($this->a)->get(route('academic.bundles.index'))->assertForbidden();
});
