<?php

use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M03\UpdateCourse;
use App\Actions\M03\UpdateCoursePricing;
use App\Actions\M07\ExpireOrder;
use App\Actions\M07\PlaceOrder;
use App\Enums\M01\RoleCode;
use App\Enums\M03\LearningMode;
use App\Enums\M07\OrderStatus as DH;
use App\Enums\M07\PaymentReconcileStatus as DS;
use App\Enums\M08\EnrollmentValidity as HL;
use App\Enums\M08\LearningStatus;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use App\Models\M07\PaymentEvent;
use App\Models\M08\Enrollment;
use App\Models\M11\AuditLog;
use App\Support\DomainRejection;
use App\Support\M08\EnrollmentValidityChecker;
use Database\Seeders\OrderDemoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\MuaHang as MH;

pest()->group('P3');

beforeEach(function () {
    $this->d = MH::d7();
    $this->a = $this->d['a'];
    $this->b = $this->d['b'];
    $this->k = $this->d['k1'];
    $this->lan = $this->d['lan'];
    MH::luc('10:00:00');
});

afterEach(fn () => test()->travelBack());

it('AC-03-44 Khóa có ghi danh chặn đổi kiểu học, cho đổi giá', function () {
    MH::ghiDanh($this->a, $this->k);
    expect(fn () => app(UpdateCourse::class)->handle($this->lan, $this->k, ['learning_mode' => LearningMode::TuDo]))->toThrow(DomainRejection::class);
    app(UpdateCoursePricing::class)->handle($this->lan, $this->k, 449000, 699000, null);
    expect($this->k->fresh()->price)->toBe(449000)->and($this->k->fresh()->learning_mode)->toBe(LearningMode::TuanTu)
        ->and(AuditLog::query()->where('action', 'COURSE_PRICE')->exists())->toBeTrue();
});

it('AC-05-04 Ghi danh mới sau công bố gắn bản 2', function () {
    $v2 = MH::congBoMoi($this->k, $this->d['t1'], $this->d['minh'], $this->lan);
    $order = MH::dat($this->b, $this->k);
    MH::xacNhan(MH::khoiTao($this->b, $order));
    MH::chayCap();
    expect(Enrollment::query()->sole()->course_version_id)->toBe($v2->id);
});

it('AC-05-08 Mua trước công bố bản 2 giữ ghi danh đã cấp trên bản 1', function () {
    $order = MH::dat($this->b, $this->k);
    $v1 = $this->k->current_version_id;
    MH::xacNhan(MH::khoiTao($this->b, $order));
    MH::chayCap();
    MH::congBoMoi($this->k, $this->d['t1'], $this->d['minh'], $this->lan);
    expect(Enrollment::query()->sole()->course_version_id)->toBe($v1);
});

it('AC-05-12 Không tạo đơn mới khi khóa ngừng bán', function () {
    app(ChangeSaleStatus::class)->stop($this->lan, $this->k);
    expect(fn () => app(PlaceOrder::class)->handle($this->b, $this->k))->toThrow(DomainRejection::class)
        ->and(Order::query()->count())->toBe(0);
});

it('AC-05-14 Đơn trước ngừng bán còn hạn thanh toán và cấp được', function () {
    $order = MH::dat($this->a, $this->k);
    app(ChangeSaleStatus::class)->stop($this->lan, $this->k);
    MH::luc('10:10:00');
    MH::xacNhan(MH::khoiTao($this->a, $order));
    MH::chayCap();
    expect($order->fresh()->status)->toBe(DH::HoanTat)->and(Enrollment::query()->count())->toBe(1);
});

it('AC-05-21 Đơn mới dùng giá mới, đơn cũ giữ giá chụp', function () {
    $old = MH::dat($this->a, $this->k);
    app(UpdateCoursePricing::class)->handle($this->lan, $this->k, 449000, 699000, null);
    $new = MH::dat($this->b, $this->k);
    expect(MH::dong($new)->final_amount)->toBe(449000)->and(MH::dong($old)->final_amount)->toBe(499000)
        ->and($old->fresh()->total)->toBe(499000);
});

it('AC-05-30 Khóa ngừng bán rời danh mục và từ chối mua trực tiếp', function () {
    app(ChangeSaleStatus::class)->stop($this->lan, $this->k);
    $catalog = $this->get(route('public.courses.index'))->assertOk()->viewData('page')['props'];
    expect(array_column($catalog['courses']['data'], 'slug'))->not->toContain($this->k->slug);
    $page = $this->get(route('public.courses.show', $this->k->slug))->assertOk()->viewData('page')['props'];
    expect($page['course']['sale_status_label'])->toBe('Ngừng bán')->and($page['cta']['buy'])->toBeFalse();
    $this->signIn($this->b)->postJson(route('student.orders.store', $this->k->slug))->assertUnprocessable();
});

it('AC-05-31 Khoản đến sau hạn khi khóa đã ngừng bán chờ Giáo vụ', function () {
    $order = MH::dat($this->a, $this->k);
    $payment = MH::khoiTao($this->a, $order);
    app(ChangeSaleStatus::class)->stop($this->lan, $this->k);
    MH::luc('10:31:00');
    MH::xacNhan($payment);
    expect($order->fresh()->status)->toBe(DH::CanXuLy)->and($payment->fresh()->reconcile_status)->toBe(DS::CanHoan)
        ->and(MH::soViec())->toBe(0);
});

it('AC-05-34 Đúng hạn giữ khoản hợp lệ, một giây sau là khoản muộn', function () {
    $first = MH::dat($this->a, $this->k);
    $second = MH::dat($this->b, $this->k);
    $a = MH::khoiTao($this->a, $first);
    $b = MH::khoiTao($this->b, $second);
    app(ChangeSaleStatus::class)->stop($this->lan, $this->k);
    MH::luc('10:30:00');
    MH::xacNhan($a);
    MH::chayCap();
    MH::luc('10:30:01');
    app(ExpireOrder::class)->handle($second->id);
    MH::xacNhan($b);
    expect($first->fresh()->status)->toBe(DH::HoanTat)->and($a->fresh()->reconcile_status)->toBe(DS::HopLe)
        ->and($second->fresh()->status)->toBe(DH::CanXuLy)->and($b->fresh()->reconcile_status)->toBe(DS::CanHoan);
});

it('AC-06-19 CTA theo hiệu lực còn hạn hoặc đã hết hạn', function () {
    MH::ghiDanh($this->a, $this->k, ['het_han_luc' => now()->addYear()]);
    MH::ghiDanh($this->b, $this->k, ['het_han_luc' => now()->subDays(17)]);
    foreach ([[$this->a, 'Vào học'], [$this->b, 'Đã hết hạn truy cập']] as [$student, $label]) {
        $props = $this->signIn($student)->get(route('public.courses.show', $this->k->slug))->assertOk()->viewData('page')['props'];
        expect($props['cta']['label'])->toBe($label)->and($props['cta']['buy'])->toBeFalse();
    }
});

it('AC-06-33 Đúng giờ hết hạn, CTA học đổi thành nhãn hết hạn', function () {
    MH::ghiDanh($this->b, $this->k, ['het_han_luc' => now()]);
    $this->signIn($this->b);
    $url = route('public.courses.show', $this->k->slug);
    MH::luc('09:59:59');
    expect($this->get($url)->assertOk()->viewData('page')['props']['cta']['label'])->toBe('Vào học');
    MH::luc('10:00:00');
    expect($this->get($url)->assertOk()->viewData('page')['props']['cta']['label'])->toBe('Đã hết hạn truy cập');
});

it('AC-06-34 Đơn cần xử lý mở đúng chi tiết và không hiện thanh toán', function () {
    $order = MH::dat($this->b, $this->k);
    $order->update(['status' => DH::CanXuLy]);
    $this->signIn($this->b)->get(route('public.courses.buy', $this->k->slug))->assertRedirect(route('student.orders.show', $order));
    expect($this->get(route('student.orders.show', $order))->assertOk()->viewData('page')['props']['order']['can_pay'])->toBeFalse();
});

it('AC-06-39 Đơn đang cấp quyền mở đúng chi tiết và không tạo đơn mới', function () {
    $order = MH::dat($this->b, $this->k);
    MH::xacNhan(MH::khoiTao($this->b, $order));
    $this->signIn($this->b)->get(route('public.courses.buy', $this->k->slug))->assertRedirect(route('student.orders.show', $order));
    expect(Order::query()->count())->toBe(1)
        ->and($this->get(route('student.orders.show', $order))->assertOk()->viewData('page')['props']['order']['can_pay'])->toBeFalse();
});

it('AC-15-01 Hiệu lực đổi đúng mốc, trạng thái học không đổi', function () {
    $enrollment = MH::ghiDanh($this->a, $this->k, ['het_han_luc' => now()->addYear()]);
    MH::luc('2027-11-18 09:59:59');
    expect(EnrollmentValidityChecker::for($enrollment))->toBe(HL::ConHan);
    MH::luc('2027-11-18 10:00:00');
    expect(EnrollmentValidityChecker::for($enrollment))->toBe(HL::HetHan)
        ->and($enrollment->fresh()->learning_status)->toBe(LearningStatus::DangHoc);
});

it('AC-15-03 Ghi danh vĩnh viễn không hết hạn sau mười năm', function () {
    $this->k->update(['access_months' => null]);
    $order = MH::dat($this->a, $this->k);
    MH::xacNhan(MH::khoiTao($this->a, $order));
    MH::chayCap();
    MH::luc('2036-11-18 10:00:00');
    $enrollment = Enrollment::query()->sole();
    expect($enrollment->het_han_luc)->toBeNull()->and(EnrollmentValidityChecker::for($enrollment))->toBe(HL::ConHan);
});

it('P3 Seed demo tạo đủ ca và chạy lần hai không nhân bản', function () {
    app(ChangeSaleStatus::class)->start($this->lan, $this->d['k4']);
    app(ChangeSaleStatus::class)->resume($this->lan, $this->d['k5']);
    $demo = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);
    $this->seed(OrderDemoSeeder::class);
    $this->seed(OrderDemoSeeder::class);
    expect(Order::query()->where('user_id', $demo->id)->count())->toBe(5)
        ->and(Enrollment::query()->where('user_id', $demo->id)->count())->toBe(1)
        ->and(Payment::query()->where('reconcile_status', DS::CanHoan)->count())->toBe(2)
        ->and(PaymentEvent::query()->count())->toBe(4);
});

it('TV-09 Chỉ dọn tác vụ lỗi và phiên quá hạn, giữ nhật ký', function () {
    config(['session.driver' => 'database']);
    $db = DB::class;
    $db::table('failed_jobs')->insert([
        ['uuid' => 'cu', 'connection' => 'database', 'queue' => 'grants', 'payload' => '{}', 'exception' => 'lỗi', 'failed_at' => now()->subHours(721)],
        ['uuid' => 'moi', 'connection' => 'database', 'queue' => 'grants', 'payload' => '{}', 'exception' => 'lỗi', 'failed_at' => now()->subHours(719)],
    ]);
    $db::table('sessions')->insert([
        ['id' => 'cu', 'payload' => '', 'last_activity' => now()->subMinutes(config('session.lifetime') + 1)->timestamp],
        ['id' => 'moi', 'payload' => '', 'last_activity' => now()->timestamp],
    ]);
    $audit = AuditLog::query()->count();
    $this->artisan('edunest:prune-operational-data')->assertSuccessful();
    expect($db::table('failed_jobs')->pluck('uuid')->all())->toBe(['moi'])
        ->and($db::table('sessions')->pluck('id')->all())->toBe(['moi'])
        ->and(AuditLog::query()->count())->toBe($audit);
});
