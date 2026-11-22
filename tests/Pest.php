<?php

use App\Enums\M01\RoleCode;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Đồng thời (07 §5): dữ liệu phải commit thật để tiến trình thứ hai thấy — không bọc giao dịch.
pest()->extend(TestCase::class)->in('Concurrency');

// Trình duyệt thật (pest-plugin-browser + Playwright), máy chủ chạy trong tiến trình test.
// Máy có Application Control chặn Chromium của Playwright: chỉ chạy khi đặt EDUNEST_BROWSER=1.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->group('browser')
    ->beforeEach(function () {
        if (! getenv('EDUNEST_BROWSER')) {
            $this->markTestSkipped('Cần EDUNEST_BROWSER=1 và Chromium của Playwright chạy được.');
        }
    })
    ->in('Browser');

/** Tạo tài khoản theo email cố định của AC (07 §3: mọi fixture dùng @edunest.test). */
function taiKhoan(string $email, RoleCode ...$roles): User
{
    $factory = User::factory()->state(['email' => $email]);
    foreach ($roles as $r) {
        if ($r->requiresTwoFactor()) {
            $factory = $factory->withTwoFactor();
            break;
        }
    }

    return $factory->role(...$roles)->create();
}

/** Mã TOTP hiện tại của tài khoản đã có bí mật 2FA. */
function maOtp(User $user): string
{
    $user->refresh();

    return app(Google2FA::class)->getCurrentOtp(decrypt((string) $user->two_factor_secret));
}

/** Thiết lập 2FA qua tuyến Fortify như người dùng (bật → quét → xác nhận). */
function thietLap2fa(User $user): void
{
    test()->post(route('two-factor.enable'))->assertSessionHasNoErrors();
    test()->post(route('two-factor.confirm'), ['code' => maOtp($user)])->assertSessionHasNoErrors();
    $user->refresh();
    expect($user->two_factor_confirmed_at)->not->toBeNull();
}

function dangNhap(string $email, string $password = UserFactory::PASSWORD, ?string $ip = null): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip ?? '127.0.0.1'])
        ->post(route('login.store'), ['email' => $email, 'password' => $password]);
}

function dangXuat(): void
{
    if (auth()->check()) {
        test()->post(route('logout'));
    }
    auth()->forgetGuards();
}
