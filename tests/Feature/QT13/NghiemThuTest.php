<?php

use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M07\CancelOrder;
use App\Actions\M07\PlaceOrder;
use App\Actions\M12\ApplyCoupon;
use App\Actions\M12\ManageCart;
use App\Actions\M12\PlaceCartOrder;
use App\Actions\M12\SaveCoupon;
use App\Actions\M12\SetCoursePromotion;
use App\Enums\M01\RoleCode;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentReconcileStatus;
use App\Enums\M12\CouponSlotStatus;
use App\Models\M07\Order;
use App\Models\M11\AuditLog;
use App\Models\M12\CartItem;
use App\Models\M12\CouponRedemption;
use App\Support\DomainRejection;
use App\Support\M12\Commerce;
use Tests\Support\MuaHang as MH;
use Tests\Support\ThuongMai as TM;

pest()->group('P5');

beforeEach(function () {
    $this->d = TM::d13();
    $this->a = $this->d['a'];
    $this->b = $this->d['b'];
    $this->c = $this->d['lan'];
    $this->k1 = $this->d['k1'];
    $this->k3 = $this->d['k3'];
    $this->k2 = TM::gia($this->d['k2'], 301000);
});

afterEach(fn () => $this->travelBack());

function gioP5(object $t, array $courses): array
{
    foreach ($courses as $course) {
        app(ManageCart::class)->add($t->a, $course);
    }

    return app(PlaceCartOrder::class)->handle($t->a);
}

function apMa(object $t, Order $order, string $code): Order
{
    return app(ApplyCoupon::class)->handle($t->a, $order, $code);
}

it('AC-13-01 Đặt giá khuyến mãi, trang khóa hiện giá mới và nhật ký đổi giá', function () {
    app(SetCoursePromotion::class)->handle($this->c, $this->k1, ['price' => 399000, 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-22']);
    expect(Commerce::effectivePrice($this->k1->fresh())[0])->toBe(399000)
        ->and(AuditLog::query()->where('action', 'COURSE_PROMOTION_SET')->exists())->toBeTrue();
    $this->get(route('public.courses.show', $this->k1->slug))->assertOk()
        ->assertInertia(fn ($p) => $p->where('pricing.price', 399000)->where('pricing.list_price', 499000));
});

it('AC-13-02 AC-13-03 Biên giá và khoảng ngày khuyến mãi', function () {
    $set = fn (int $price, string $s, string $e) => app(SetCoursePromotion::class)->handle($this->c, $this->k1, ['price' => $price, 'starts_on' => $s, 'ends_on' => $e]);
    expect(fn () => $set(499000, '2026-11-20', '2026-11-22'))->toThrow(DomainRejection::class)
        ->and(fn () => $set(499001, '2026-11-20', '2026-11-22'))->toThrow(DomainRejection::class)
        ->and(fn () => $set(399000, '2026-11-20', '2026-11-20'))->toThrow(DomainRejection::class)
        ->and(fn () => $set(399000, '2026-11-20', '2026-11-19'))->toThrow(DomainRejection::class);
    expect($set(498999, '2026-11-20', '2026-11-21')->price)->toBe(498999);
});

it('AC-13-04 AC-13-05 Khuyến mãi gồm trọn ngày cuối, hết lúc 00:00 ngày kế', function () {
    app(SetCoursePromotion::class)->handle($this->c, $this->k1, ['price' => 399000, 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-22']);
    MH::luc('2026-11-22 23:59:59');
    expect(MH::dat($this->a, $this->k1)->items()->sole()->effective_price)->toBe(399000);
    MH::luc('2026-11-23 00:00:00');
    expect(MH::dat($this->b, $this->k1)->items()->sole()->effective_price)->toBe(499000)
        ->and(Commerce::effectivePrice($this->k1->fresh())[1])->toBeNull();
});

it('AC-13-06 AC-13-10 Sai vai không đặt khuyến mãi, không tạo mã', function () {
    $this->signIn($this->a)->post(route('academic.promotions.store'), ['course_id' => $this->k1->id, 'price' => 1, 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-22'])->assertForbidden();
    $this->signIn($this->d['t1'])->post(route('academic.coupons.store'), ['code' => 'GV10'])->assertForbidden();
    expect(Commerce::effectivePrice($this->k1->fresh())[1])->toBeNull();
});

it('AC-13-07 AC-13-08 AC-13-09 Tạo mã, biên phần trăm và mã trùng sau chuẩn hóa', function () {
    expect(TM::ma($this->c)->code)->toBe('HOA12');
    expect(fn () => TM::ma($this->c, ['code' => ' hoa12 ']))->toThrow(DomainRejection::class);
    expect(TM::ma($this->c, ['code' => 'PCT1', 'value' => 1])->value)->toBe(1)
        ->and(TM::ma($this->c, ['code' => 'PCT100', 'value' => 100])->value)->toBe(100);
    expect(fn () => TM::ma($this->c, ['code' => 'PCT0', 'value' => 0]))->toThrow(DomainRejection::class)
        ->and(fn () => TM::ma($this->c, ['code' => 'PCT101', 'value' => 101]))->toThrow(DomainRejection::class);
});

it('AC-13-12 AC-13-17 Thêm và bỏ khóa trong giỏ, tổng tạm tính', function () {
    $this->signIn($this->a)->post(route('student.cart.store', $this->k1))->assertRedirect();
    $this->post(route('student.cart.store', $this->k2))->assertRedirect();
    $this->get(route('student.cart.index'))->assertInertia(fn ($p) => $p->where('subtotal', 800000)->has('items', 2));
    $this->delete(route('student.cart.destroy', $this->k2))->assertRedirect();
    expect(CartItem::query()->pluck('course_id')->all())->toBe([$this->k1->id]);
});

it('AC-13-14 AC-13-15 Không thêm khóa đã sở hữu, không bán hoặc đã có trong giỏ', function () {
    MH::ghiDanh($this->a, $this->k1);
    expect(fn () => app(ManageCart::class)->add($this->a, $this->k1))->toThrow(DomainRejection::class, 'đã sở hữu');
    expect(fn () => app(ManageCart::class)->add($this->a, $this->d['k5']))->toThrow(DomainRejection::class)
        ->and(fn () => app(ManageCart::class)->add($this->a, $this->d['k4']))->toThrow(DomainRejection::class);
    app(ManageCart::class)->add($this->a, $this->k3);
    expect(fn () => app(ManageCart::class)->add($this->a, $this->k3))->toThrow(DomainRejection::class);
    expect(CartItem::query()->count())->toBe(1);
});

it('AC-13-19 Tài khoản chỉ có vai Giáo vụ không có giỏ', function () {
    $this->signIn($this->c)->post(route('student.cart.checkout'))->assertForbidden();
    expect(Order::query()->count())->toBe(0);
});

it('AC-13-21 AC-13-16 Thanh toán giỏ tạo một đơn nhiều dòng, khóa đã sở hữu rời giỏ', function () {
    app(ManageCart::class)->add($this->a, $this->k1);
    app(ManageCart::class)->add($this->a, $this->k2);
    app(ManageCart::class)->add($this->a, $this->k3);
    MH::ghiDanh($this->a, $this->k3);
    $r = app(PlaceCartOrder::class)->handle($this->a);
    $order = $r['order'];
    expect($order->status)->toBe(OrderStatus::ChoThanhToan)->and($order->total)->toBe(800000)
        ->and($order->items()->count())->toBe(2)->and($order->items()->pluck('access_months')->unique()->all())->toBe([12])
        ->and(CartItem::query()->count())->toBe(0);
});

it('AC-13-23 AC-13-24 Làm mới giỏ khi ngừng bán; đơn chờ cùng khóa chặn cả giỏ', function () {
    app(ManageCart::class)->add($this->a, $this->k1);
    app(ManageCart::class)->add($this->a, $this->k3);
    $open = MH::dat($this->a, $this->k1);
    $r = app(PlaceCartOrder::class)->handle($this->a);
    expect($r['order'])->toBeNull()->and($r['blocked'][0]->id)->toBe($open->id)->and(CartItem::query()->count())->toBe(2);
    app(CancelOrder::class)->handle($this->a, $open);
    app(ChangeSaleStatus::class)->stop($this->c, $this->k3->fresh());
    $r = app(PlaceCartOrder::class)->handle($this->a);
    expect($r['order']->items()->pluck('course_id')->all())->toBe([$this->k1->id])->and($r['excluded'])->toHaveCount(1);
});

it('AC-13-25 Bấm thanh toán giỏ hai lần chỉ có một đơn', function () {
    app(ManageCart::class)->add($this->a, $this->k1);
    app(PlaceCartOrder::class)->handle($this->a);
    expect(fn () => app(PlaceCartOrder::class)->handle($this->a))->toThrow(DomainRejection::class, 'trống');
    expect(Order::query()->count())->toBe(1);
});

it('AC-13-26 Áp mã cắt trần, phân bổ theo giá dòng', function () {
    TM::ma($this->c);
    $order = gioP5($this, [$this->k1, $this->k2])['order'];
    $order = apMa($this, $order, ' hoa12 ');
    $items = $order->items()->orderBy('sort_order')->get();
    expect($order->discount_total)->toBe(100000)->and($order->total)->toBe(700000)
        ->and($items[0]->allocated_discount)->toBe(62375)->and($items[1]->allocated_discount)->toBe(37625)
        ->and($items[0]->final_amount)->toBe(436625);
});

it('AC-13-27 AC-13-29 AC-13-31 Dưới trần, làm tròn xuống, sàn 0', function () {
    TM::ma($this->c);
    expect(apMa($this, MH::dat($this->a, $this->k1), 'HOA12')->total)->toBe(399200);
    TM::ma($this->c, ['code' => 'HOA15', 'value' => 15]);
    $k11 = TM::gia($this->k3, 333333);
    expect(app(ApplyCoupon::class)->handle($this->b, MH::dat($this->b, $k11), 'HOA15')->discount_total)->toBe(49999);
    TM::ma($this->c, ['code' => 'GIAM500K', 'discount_type' => 'SO_TIEN', 'value' => 500000, 'max_discount' => null, 'min_order' => 400000, 'ends_on' => '2026-12-31']);
    $e = taiKhoan('e@edunest.test', RoleCode::HocVien);
    expect(app(ApplyCoupon::class)->handle($e, MH::dat($e, $this->k1), 'GIAM500K')->total)->toBe(0);
});

it('AC-13-30 AC-13-38 Phạm vi khóa và danh mục cấp 1 gồm danh mục con', function () {
    TM::ma($this->c, ['code' => 'GIAM50K', 'discount_type' => 'SO_TIEN', 'value' => 50000, 'max_discount' => null, 'scope_type' => 'KHOA', 'scope_course_id' => $this->k1->id, 'min_order' => 400000, 'total_limit' => 10]);
    $order = apMa($this, gioP5($this, [$this->k1, $this->k3])['order'], 'GIAM50K');
    expect($order->total)->toBe(848000)->and($order->items()->where('course_id', $this->k3->id)->value('allocated_discount'))->toBe(0);
    TM::ma($this->c, ['code' => 'HOADM', 'value' => 10, 'max_discount' => 50000, 'scope_type' => 'DANH_MUC', 'scope_category_id' => $this->d['tn']->id, 'min_order' => 100000]);
    expect(app(ApplyCoupon::class)->handle($this->b, MH::dat($this->b, $this->k1), 'HOADM')->total)->toBe(449100);
});

it('AC-13-33 AC-13-34 Đơn tối thiểu so trên giá sau khuyến mãi', function () {
    TM::ma($this->c, ['code' => 'GIAM50K', 'discount_type' => 'SO_TIEN', 'value' => 50000, 'max_discount' => null, 'min_order' => 400000]);
    app(SetCoursePromotion::class)->handle($this->c, $this->k1, ['price' => 399000, 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-22']);
    $order = MH::dat($this->a, $this->k1);
    expect(fn () => apMa($this, $order, 'GIAM50K'))->toThrow(DomainRejection::class, 'tối thiểu');
    expect($order->fresh()->total)->toBe(399000)->and($order->fresh()->coupon_id)->toBeNull();
});

it('AC-13-35 Hiệu lực mã gồm hai đầu ngày', function () {
    TM::ma($this->c);
    MH::luc('2026-11-14 23:59:59');
    $order = MH::dat($this->a, $this->k1);
    expect(fn () => apMa($this, $order, 'HOA12'))->toThrow(DomainRejection::class, 'chưa tới hạn');
    MH::luc('2026-11-30 23:59:59');
    expect(app(ApplyCoupon::class)->handle($this->b, MH::dat($this->b, $this->k1), 'HOA12')->coupon_id)->not->toBeNull();
    MH::luc('2026-12-01 00:00:00');
    $e = taiKhoan('e@edunest.test', RoleCode::HocVien);
    expect(fn () => app(ApplyCoupon::class)->handle($e, MH::dat($e, $this->k1), 'HOA12'))->toThrow(DomainRejection::class, 'hết hạn');
});

it('AC-13-36 AC-13-37 DT-38 Hết lượt tổng và lượt mỗi người, chỗ giữ tính vào hạn mức', function () {
    TM::ma($this->c, ['total_limit' => 1]);
    apMa($this, MH::dat($this->a, $this->k1), 'HOA12');
    expect(fn () => app(ApplyCoupon::class)->handle($this->b, MH::dat($this->b, $this->k1), 'HOA12'))->toThrow(DomainRejection::class, 'hết lượt');
    TM::ma($this->c, ['code' => 'NGUOI1', 'total_limit' => 10]);
    apMa($this, MH::dat($this->a, $this->k3), 'NGUOI1');
    expect(fn () => apMa($this, MH::dat($this->a, $this->k2), 'NGUOI1'))->toThrow(DomainRejection::class, 'hết lượt của mã');
});

it('AC-13-39 AC-13-40 Không áp mã thứ hai, không áp cho đơn gia hạn hoặc đơn đã có khoản', function () {
    TM::ma($this->c);
    TM::ma($this->c, ['code' => 'GIAM50K', 'discount_type' => 'SO_TIEN', 'value' => 50000, 'max_discount' => null]);
    $order = apMa($this, MH::dat($this->a, $this->k1), 'HOA12');
    expect(fn () => apMa($this, $order, 'GIAM50K'))->toThrow(DomainRejection::class, 'chỉ áp một mã');
    $other = MH::dat($this->b, $this->k1);
    MH::khoiTao($this->b, $other);
    expect(fn () => app(ApplyCoupon::class)->handle($this->b, $other, 'HOA12'))->toThrow(DomainRejection::class, 'khoản thanh toán');
});

it('AC-13-32 CR-120 Đơn 0đ đi qua cổng, khoản hợp lệ tiêu đúng một lượt; hủy đơn trả chỗ', function () {
    TM::ma($this->c, ['code' => 'GIAM500K', 'discount_type' => 'SO_TIEN', 'value' => 500000, 'max_discount' => null, 'min_order' => 400000]);
    $order = apMa($this, MH::dat($this->a, $this->k1), 'GIAM500K');
    $payment = MH::khoiTao($this->a, $order);
    MH::xacNhan($payment, 0);
    MH::xacNhan($payment, 0);
    expect($payment->fresh()->reconcile_status)->toBe(PaymentReconcileStatus::HopLe)
        ->and(CouponRedemption::query()->sole()->status)->toBe(CouponSlotStatus::DaDung);
    TM::ma($this->c);
    $other = apMa($this, MH::dat($this->a, $this->k3), 'HOA12');
    app(CancelOrder::class)->handle($this->a, $other);
    expect(CouponRedemption::query()->where('order_id', $other->id)->sole()->status)->toBe(CouponSlotStatus::DaTra);
});

it('AC-13-11 Đơn đã áp mã giữ mức giảm khi giáo vụ rút ngắn hạn mã', function () {
    $coupon = TM::ma($this->c);
    $order = apMa($this, MH::dat($this->a, $this->k1), 'HOA12');
    app(SaveCoupon::class)->handle($this->c, ['code' => 'HOA12', 'discount_type' => 'PHAN_TRAM', 'value' => 20, 'max_discount' => 100000,
        'starts_on' => '2026-11-15', 'ends_on' => '2026-11-19', 'total_limit' => 100, 'per_user_limit' => 1, 'scope_type' => 'TOAN_BO',
        'scope_category_id' => null, 'scope_course_id' => null, 'min_order' => 300000, 'note' => null], $coupon);
    expect($order->fresh()->total)->toBe(399200);
    expect(fn () => app(ApplyCoupon::class)->handle($this->b, MH::dat($this->b, $this->k1), 'HOA12'))->toThrow(DomainRejection::class, 'hết hạn');
});

it('Phân bổ CR-124: 100 dòng 1đ giảm 99đ, hai dòng bằng nhau dòng trước nhận đồng dư', function () {
    $r = Commerce::allocate(99, array_fill(0, 100, 1));
    expect(array_sum($r))->toBe(99)->and($r[99])->toBe(0);
    $r = Commerce::allocate(999999, [10 => 600000, 11 => 600000]);
    expect($r)->toBe([10 => 500000, 11 => 499999]);
});

it('Mua ngay chụp giá khuyến mãi và mã khuyến mãi lên dòng đơn', function () {
    $promo = app(SetCoursePromotion::class)->handle($this->c, $this->k1, ['price' => 399000, 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-22']);
    $item = app(PlaceOrder::class)->handle($this->a, $this->k1)->items()->sole();
    expect($item->promotion_id)->toBe($promo->id)->and($item->list_price)->toBe(499000)->and($item->final_amount)->toBe(399000);
});
