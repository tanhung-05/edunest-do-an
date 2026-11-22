<?php

use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M12\ManageCart;
use App\Actions\M12\PlaceCartOrder;
use App\Actions\M13\ActivationCodes;
use App\Actions\M13\RedeemActivationCode;
use App\Enums\M01\RoleCode;
use App\Enums\M08\EnrollmentSource;
use App\Enums\M13\ActivationCodeStatus;
use App\Models\M08\Enrollment;
use App\Models\M13\ActivationAttemptWindow;
use App\Models\M13\ActivationCode;
use App\Models\M13\ActivationCodeBatch;
use App\Support\DomainRejection;
use Carbon\CarbonImmutable;
use Tests\Support\MuaHang as MH;
use Tests\Support\ThuongMai as TM;

pest()->group('P5');

beforeEach(function () {
    $this->d = TM::d13();
    $this->a = $this->d['a'];
    $this->b = $this->d['b'];
    $this->c = $this->d['lan'];
    $this->k1 = $this->d['k1'];
});

afterEach(fn () => $this->travelBack());

function loP5(object $t, int $n = 5, string $until = '2026-12-31 23:59:59'): ActivationCodeBatch
{
    return app(ActivationCodes::class)->createBatch($t->c, $t->k1, $n, CarbonImmutable::parse($until, 'Asia/Ho_Chi_Minh')->utc(), 'kèm sách Hóa 12');
}

function maP5(ActivationCodeBatch $batch, int $i = 0): ActivationCode
{
    return $batch->codes()->orderBy('id')->get()[$i];
}

function doiP5(object $t, string $code, $user = null): Enrollment
{
    return app(RedeemActivationCode::class)->handle($user ?? $t->a, $code);
}

it('AC-14-01 Tạo lô 50 mã chụp số tháng, mã 12 ký tự chữ hoa, duy nhất', function () {
    $batch = loP5($this, 50);
    $codes = $batch->codes()->pluck('code');
    expect($codes)->toHaveCount(50)->and($codes->unique())->toHaveCount(50)->and($batch->access_months)->toBe(12)
        ->and($codes->every(fn ($c) => preg_match('/^[2-9A-HJKMNP-Z]{12}$/', $c) === 1))->toBeTrue();
});

it('AC-14-02 AC-14-03 AC-14-50 CR-130 Biên số lượng và hạn đổi bằng giờ hiện tại', function () {
    expect(fn () => loP5($this, 0))->toThrow(DomainRejection::class)
        ->and(fn () => loP5($this, 1001))->toThrow(DomainRejection::class)
        ->and(fn () => loP5($this, 1, '2026-11-20 10:00:00'))->toThrow(DomainRejection::class);
    expect(loP5($this, 1, '2026-11-20 10:00:01')->quantity)->toBe(1);
});

it('AC-14-06 AC-14-16 AC-14-35 Đổi mã tạo ghi danh nguồn mã kích hoạt; nhập lại mã đã dùng bị từ chối', function () {
    $code = maP5(loP5($this));
    $e = doiP5($this, ' '.strtolower($code->code).' ');
    expect($e->source)->toBe(EnrollmentSource::MaKichHoat)->and($e->het_han_luc->equalTo(now()->addMonthsNoOverflow(12)))->toBeTrue()
        ->and($code->fresh()->status)->toBe(ActivationCodeStatus::DaDung);
    expect(fn () => doiP5($this, $code->code, $this->b))->toThrow(DomainRejection::class, 'đã được sử dụng');
});

it('AC-14-08 AC-14-09 Ghi danh còn hạn: từ chối, mã không bị tiêu; hết hạn: cộng hạn từ lúc đổi', function () {
    $batch = loP5($this);
    $e = MH::ghiDanh($this->a, $this->k1, ['het_han_luc' => now()->addDays(3)]);
    expect(fn () => doiP5($this, maP5($batch)->code))->toThrow(DomainRejection::class, 'còn hạn');
    expect(maP5($batch)->fresh()->status)->toBe(ActivationCodeStatus::ChuaDung);
    $e->update(['het_han_luc' => now()->subDay()]);
    $after = doiP5($this, maP5($batch)->code);
    expect($after->id)->toBe($e->id)->and($after->het_han_luc->equalTo(now()->addMonthsNoOverflow(12)))->toBeTrue();
});

it('AC-14-17 AC-14-19 AC-14-52 Mã quá hạn đổi, mã bị vô hiệu bị từ chối và không tính lần sai', function () {
    $batch = loP5($this, 3, '2026-11-25 00:00:00');
    app(ActivationCodes::class)->void($this->c, $batch, maP5($batch), 'Phát nhầm', true);
    expect(fn () => doiP5($this, maP5($batch)->code))->toThrow(DomainRejection::class, 'vô hiệu');
    MH::luc('2026-11-26 10:00:00');
    expect(fn () => doiP5($this, maP5($batch, 1)->code))->toThrow(DomainRejection::class, 'hết hạn đổi');
    expect(ActivationAttemptWindow::query()->where('user_id', $this->a->id)->value('failures'))->toBe(0);
});

it('AC-14-22 AC-14-23 CR-129 Sai 5 lần trong 900 giây khóa 15 phút, cả mã đúng bị từ chối; hết khóa nhận lại', function () {
    $good = maP5(loP5($this))->code;
    foreach (['10:00:00', '10:03:00', '10:06:00', '10:09:00', '10:12:00'] as $t) {
        MH::luc('2026-11-20 '.$t);
        expect(fn () => doiP5($this, 'SAIMA0000000'))->toThrow(DomainRejection::class);
    }
    MH::luc('2026-11-20 10:20:00');
    expect(fn () => doiP5($this, $good))->toThrow(DomainRejection::class, 'Thử lại sau');
    MH::luc('2026-11-20 10:27:00');
    expect(doiP5($this, $good)->course_id)->toBe($this->k1->id);
});

it('AC-14-24 Cửa sổ quá 900 giây mở cửa sổ mới', function () {
    foreach (['10:00:00', '10:03:00', '10:06:00', '10:09:00', '10:15:01'] as $t) {
        MH::luc('2026-11-20 '.$t);
        expect(fn () => doiP5($this, 'SAIMA0000000'))->toThrow(DomainRejection::class);
    }
    $w = ActivationAttemptWindow::query()->sole();
    expect($w->failures)->toBe(1)->and($w->locked_until)->toBeNull();
});

it('AC-14-15 Khóa ngừng bán vẫn đổi được; khóa chưa bán bị từ chối', function () {
    $batch = loP5($this);
    app(ChangeSaleStatus::class)->stop($this->c, $this->k1->fresh());
    expect(doiP5($this, maP5($batch)->code)->course_id)->toBe($this->k1->id);
});

it('AC-14-34 A12 Có đơn MUA_KHOA mở cùng khóa thì chặn đổi mã, mã giữ nguyên', function () {
    $batch = loP5($this);
    MH::dat($this->a, $this->k1);
    expect(fn () => doiP5($this, maP5($batch)->code))->toThrow(DomainRejection::class, 'đang có đơn');
    expect(maP5($batch)->fresh()->status)->toBe(ActivationCodeStatus::ChuaDung);
});

it('AC-14-20 DT-39 Hai học viên đổi cùng mã: một thành công', function () {
    $code = maP5(loP5($this))->code;
    doiP5($this, $code);
    expect(fn () => doiP5($this, $code, $this->b))->toThrow(DomainRejection::class);
    expect(Enrollment::query()->count())->toBe(1);
});

it('AC-14-18 AC-14-29 AC-14-51 A11 Vô hiệu cả lô chỉ đổi mã chưa dùng; thống kê bốn nhóm CR-132', function () {
    $batch = loP5($this, 4, '2026-11-25 00:00:00');
    doiP5($this, maP5($batch)->code);
    $n = app(ActivationCodes::class)->void($this->c, $batch, null, 'Thu hồi lô', true);
    expect($n)->toBe(3)->and(maP5($batch)->fresh()->status)->toBe(ActivationCodeStatus::DaDung)
        ->and(ActivationCodes::stats($batch))->toBe(['used' => 1, 'remaining' => 0, 'expired' => 0, 'voided' => 3]);
    $other = loP5($this, 3, '2026-11-25 00:00:00');
    MH::luc('2026-11-26 00:00:00');
    expect(ActivationCodes::stats($other))->toBe(['used' => 0, 'remaining' => 0, 'expired' => 3, 'voided' => 0]);
    expect(app(ActivationCodes::class)->expireOverdue())->toBe(3);
});

it('AC-14-25 AC-14-26 Sai vai: học viên không tạo lô; nhân sự không đổi mã', function () {
    $this->signIn($this->a)->post(route('academic.activation-batches.store'), ['course_id' => $this->k1->id, 'quantity' => 1, 'redeem_until' => '2026-12-01'])->assertForbidden();
    $this->signIn($this->c)->post(route('student.activation.store'), ['code' => 'X'])->assertForbidden();
    expect(ActivationCodeBatch::query()->count())->toBe(0);
});

it('AC-14-05 Xuất CSV danh sách mã của lô', function () {
    $batch = loP5($this, 3);
    $csv = $this->signIn($this->c)->get(route('academic.activation-batches.export', $batch))->assertOk()->streamedContent();
    expect(substr_count($csv, "\n"))->toBe(4)->and($csv)->toContain(maP5($batch)->code);
});

it('AC-13-16 DT-43 Đổi mã khi khóa đang trong giỏ: thanh toán giỏ bỏ khóa đã sở hữu', function () {
    $k2 = TM::gia($this->d['k2'], 301000);
    app(ManageCart::class)->add($this->a, $this->k1);
    app(ManageCart::class)->add($this->a, $k2);
    doiP5($this, maP5(loP5($this))->code);
    $order = app(PlaceCartOrder::class)->handle($this->a)['order'];
    expect($order->items()->pluck('course_id')->all())->toBe([$k2->id]);
});

it('Học viên chưa xác minh email không đổi được mã', function () {
    $e = taiKhoan('e2@edunest.test', RoleCode::HocVien);
    $e->forceFill(['email_verified_at' => null])->save();
    expect(fn () => doiP5($this, maP5(loP5($this))->code, $e))->toThrow(DomainRejection::class);
});
