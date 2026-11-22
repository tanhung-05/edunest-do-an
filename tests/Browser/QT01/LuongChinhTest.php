<?php

/*
| QT-01 trên trình duyệt thật (07a: AC-01-01, AC-01-16, AC-01-25).
*/

use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

pest()->group('P0');

function khongCuonNgang($page): void
{
    expect($page->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth'))->toBeTrue();
}

it('AC-01-01 Khách đăng ký, mở liên kết xác minh, đăng nhập vào trang Học viên', function () {
    Notification::fake();

    $page = visit('/register');
    $page->type('name', 'Nguyễn Văn An')
        ->type('email', 'an.nguyen@edunest.test')
        ->type('password', 'matkhau-an-123')
        ->type('password_confirmation', 'matkhau-an-123')
        ->press('Tạo tài khoản')
        ->assertPathIs('/student')
        ->assertSee('Hãy xác minh email');

    $an = User::query()->where('email', 'an.nguyen@edunest.test')->sole();
    expect($an->status)->toBe(AccountStatus::HoatDong)
        ->and($an->roleCodes())->toBe([RoleCode::HocVien]);
    Notification::assertSentToTimes($an, VerifyEmail::class, 1);

    $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $an->id, 'hash' => sha1($an->email),
    ]);
    $page->navigate($link)->assertDontSee('Hãy xác minh email');
    expect($an->fresh()->email_verified_at)->not->toBeNull();

    $page->click('Nguyễn Văn An')->click('Đăng xuất');
    $page->navigate('/login')
        ->type('email', 'an.nguyen@edunest.test')
        ->type('password', 'matkhau-an-123')
        ->press('Đăng nhập')
        ->assertPathIs('/student')
        ->assertSee('Bảng học tập');
});

it('AC-01-16 Quang chọn Khóa trong Thao tác khác rồi bấm Hủy: An vẫn hoạt động', function () {
    taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);
    $genTruoc = $an->auth_generation;

    $page = visit('/login');
    $page->type('email', 'quang@edunest.test')
        ->type('password', 'password')
        ->press('Đăng nhập')
        ->assertPathIs('/two-factor-challenge')
        ->type('code', maOtp(User::query()->where('email', 'quang@edunest.test')->sole()))
        ->press('Tiếp tục')
        ->assertPathIs('/admin');

    $page->navigate("/admin/users/{$an->id}")
        ->click('Thao tác khác')
        ->click('Khóa')
        ->assertSee('Khóa tài khoản Nguyễn Văn An?')
        ->click('Hủy')
        ->assertDontSee('Khóa tài khoản Nguyễn Văn An?')
        ->assertSee('Đang hoạt động');

    expect($an->fresh()->status)->toBe(AccountStatus::HoatDong)
        ->and($an->fresh()->auth_generation)->toBe($genTruoc);
});

it('AC-01-25 Ở 390×844, trang đăng nhập, đăng ký, đổi mật khẩu, thiết lập 2FA không cuộn ngang', function () {
    khongCuonNgang(visit('/login')->resize(390, 844));
    khongCuonNgang(visit('/register')->resize(390, 844));

    // Đổi mật khẩu bắt buộc: tài khoản được cấp mật khẩu tạm.
    User::factory()->student()->mustChangePassword()->create(['email' => 'chi.le@edunest.test']);
    $page = visit('/login')->resize(390, 844)
        ->type('email', 'chi.le@edunest.test')
        ->type('password', 'password')
        ->press('Đăng nhập')
        ->assertPathIs('/account/password/required');
    khongCuonNgang($page);

    // Thiết lập 2FA: Giáo vụ chưa bật 2FA bị đưa tới trang bảo mật.
    User::factory()->role(RoleCode::GiaoVu)->create(['email' => 'lan.pham@edunest.test']);
    $page = visit('/login')->resize(390, 844)
        ->type('email', 'lan.pham@edunest.test')
        ->type('password', 'password')
        ->press('Đăng nhập')
        ->assertPathIs('/account/security')
        ->assertSee('Thiết lập 2FA');
    khongCuonNgang($page);
});
