<?php

use App\Enums\M01\RoleCode;
use App\Http\Middleware\TrackRequestIp;
use App\Models\M01\UserDevice;
use App\Models\M11\Setting;
use App\Models\M16\SecurityAlert;
use App\Support\M09\LearningFile;
use App\Support\M16\DeviceRegistry;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\HocTap;

pest()->group('P5');

beforeEach(function () {
    $this->travelTo(HocTap::at('10:00:00'));
    Storage::fake('local');
    $this->h = new HocTap;
});

afterEach(fn () => $this->travelBack());

function dangNhapThietBi(object $t, string $email, string $cookieChar, bool $remember = false): TestResponse
{
    dangXuat();
    $t->flushSession();

    return $t->withCookie(LearningFile::DEVICE, str_repeat($cookieChar, 64))->post(route('login.store'), [
        'email' => $email, 'password' => UserFactory::PASSWORD, 'remember' => $remember,
    ]);
}

it('AC-17-03 AC-17-04 AC-17-05 Giới hạn 2 thiết bị: thiết bị thứ ba phải chọn đăng xuất một thiết bị cũ', function () {
    $email = $this->h->a->email;
    dangNhapThietBi($this, $email, 'a')->assertRedirect(route('dashboard'));
    dangNhapThietBi($this, $email, 'b')->assertRedirect(route('dashboard'));
    expect(UserDevice::query()->where('user_id', $this->h->a->id)->count())->toBe(2);
    dangNhapThietBi($this, $email, 'c')->assertRedirect(route('login.devices'));
    $this->assertGuest();
    $this->get(route('login.devices'))->assertOk()->assertInertia(fn ($p) => $p->has('devices', 2));
    $old = UserDevice::query()->where('user_id', $this->h->a->id)->orderBy('id')->first();
    $this->withCookie(LearningFile::DEVICE, str_repeat('c', 64))->post(route('login.devices.choose'), ['device' => $old->id])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
    expect($old->fresh()->revoked_at)->not->toBeNull()->and(DeviceRegistry::active($this->h->a))->toHaveCount(2);
});

it('AC-17-06 Thiết bị đã biết đăng nhập lại không cần chọn dù đã đủ giới hạn', function () {
    $email = $this->h->a->email;
    dangNhapThietBi($this, $email, 'a');
    dangNhapThietBi($this, $email, 'b');
    dangNhapThietBi($this, $email, 'a')->assertRedirect(route('dashboard'));
});

it('AC-17-07 AC-17-08 Nhân sự không giới hạn; tài khoản kiêm vai Học viên vẫn bị giới hạn', function () {
    foreach (['a', 'b', 'c', 'd'] as $ch) {
        dangNhapThietBi($this, $this->h->t->email, $ch)->assertRedirect();
        $this->assertAuthenticated();
    }
    $tv = taiKhoan('kiem-p5@edunest.test', RoleCode::GiaoVien, RoleCode::HocVien);
    dangNhapThietBi($this, $tv->email, 'a');
    dangNhapThietBi($this, $tv->email, 'b');
    dangNhapThietBi($this, $tv->email, 'c')->assertRedirect(route('login.devices'));
});

it('AC-17-09 Giảm giới hạn không đăng xuất thiết bị nào, chỉ chặn thiết bị mới', function () {
    $email = $this->h->a->email;
    dangNhapThietBi($this, $email, 'a');
    dangNhapThietBi($this, $email, 'b');
    Setting::query()->updateOrCreate(['key' => 'device.max_active'], ['value' => 1]);
    expect(DeviceRegistry::active($this->h->a))->toHaveCount(2);
    dangNhapThietBi($this, $email, 'a')->assertRedirect(route('dashboard'));
    dangNhapThietBi($this, $email, 'c')->assertRedirect(route('login.devices'));
});

it('AC-17-10 AC-17-32 Thiết bị quá 30 ngày không tính vào giới hạn; quay lại thì kiểm chỗ như thiết bị mới', function () {
    $email = $this->h->a->email;
    dangNhapThietBi($this, $email, 'a');
    UserDevice::query()->update(['last_active_at' => now()->subDays(31)]);
    dangNhapThietBi($this, $email, 'b');
    dangNhapThietBi($this, $email, 'c');
    dangNhapThietBi($this, $email, 'a')->assertRedirect(route('login.devices'));
});

it('AC-17-14 AC-17-16 Đăng xuất từ xa: phiên, token ghi nhớ và URL của thiết bị mất hiệu lực', function () {
    $email = $this->h->a->email;
    $before = $this->h->a->fresh()->remember_token;
    dangNhapThietBi($this, $email, 'b', true)->assertRedirect();
    $d2 = UserDevice::query()->where('user_id', $this->h->a->id)->sole();
    expect($d2->remember_token_hash)->not->toBeNull()->and($this->h->a->fresh()->remember_token)->toBe($before);
    $this->h->signIn($this, cookie: str_repeat('b', 64));
    $url = $this->get($this->h->lessonUrl())->assertOk()->viewData('page')['props']['video_url'];
    DeviceRegistry::revoke($this->h->a, $d2->id);
    expect($d2->fresh()->revoked_at)->not->toBeNull()->and($d2->fresh()->remember_token_hash)->toBeNull();
    $this->get($url)->assertForbidden();
});

it('AC-17-20 AC-17-37 Cookie của thiết bị đã thu hồi đăng nhập lại đi như thiết bị mới', function () {
    $email = $this->h->a->email;
    dangNhapThietBi($this, $email, 'a');
    dangNhapThietBi($this, $email, 'b');
    $d2 = UserDevice::query()->where('user_id', $this->h->a->id)->orderByDesc('id')->first();
    DeviceRegistry::revoke($this->h->a, $d2->id);
    dangNhapThietBi($this, $email, 'b')->assertRedirect(route('dashboard'));
    expect(UserDevice::query()->where('user_id', $this->h->a->id)->count())->toBe(3)->and($d2->fresh()->revoked_at)->not->toBeNull();
});

it('AC-17-22 AC-17-23 AC-17-24 Thiết bị của tôi chỉ liệt kê và đăng xuất thiết bị của mình', function () {
    dangNhapThietBi($this, $this->h->a->email, 'a');
    $d1 = UserDevice::query()->where('user_id', $this->h->a->id)->sole();
    $b = HocTap::student('binh');
    $this->signIn($b)->delete(route('account.devices.destroy', $d1->id))->assertNotFound();
    expect($d1->fresh()->revoked_at)->toBeNull();
    $this->signIn($this->h->a)->withCookie(LearningFile::DEVICE, str_repeat('a', 64))->get(route('account.devices.index'))
        ->assertOk()->assertInertia(fn ($p) => $p->has('devices', 1)->where('devices.0.current', true)->where('limit', 2));
});

it('AC-17-26 AC-17-39 Ba IP trong 10 phút cảnh báo giáo vụ một lần, không khóa tài khoản', function () {
    $a = $this->h->a;
    $t0 = now()->getTimestamp();
    TrackRequestIp::record($a, '10.0.0.1', $t0);
    TrackRequestIp::record($a, '10.0.0.2', $t0 + 300);
    expect(SecurityAlert::query()->count())->toBe(0);
    TrackRequestIp::record($a, '10.0.0.3', $t0 + 540);
    TrackRequestIp::record($a, '10.0.0.4', $t0 + 720);
    expect(SecurityAlert::query()->count())->toBe(1)->and($a->fresh()->isActive())->toBeTrue()
        ->and($this->h->g->notifications()->count())->toBe(1);
});
