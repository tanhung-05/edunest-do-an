<?php

/*
| QT-01 — Tài khoản và xác thực: tiêu chí nghiệm thu mốc P0 (07a). Phần thuộc mốc sau ghi chú tại ca.
| Dữ liệu chung: An (Học viên), Lan (Giáo vụ có 2FA), Quang (Quản trị có 2FA), Hóa (Giáo viên mới).
*/

use App\Actions\M01\ChangeAccountRoles;
use App\Actions\M01\CreateAuthenticatedSession;
use App\Actions\M01\LockAccount;
use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Models\M05\Lesson;
use App\Models\M11\AuditLog;
use App\Models\User;
use App\Support\DomainRejection;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\KhoaHoc;

pest()->group('P0');

const MAT_KHAU_MOI = 'MatKhauMoi8';

function vaiCua(User $u): array
{
    $u->forgetRoleCache();

    return array_map(fn (RoleCode $c) => $c->value, $u->roleCodes());
}

/** Phiên cũ của một tài khoản: đăng nhập với thế hệ xác thực đã ghi lúc mở phiên. */
function phienCu(User $u, int $generation): void
{
    auth()->forgetGuards();
    test()->actingAs($u->fresh())->withSession([CreateAuthenticatedSession::SESSION_KEY => $generation]);
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-23 03:00:00')); // 10:00 giờ Việt Nam
});

afterEach(function () {
    Carbon::setTestNow();
});

it('AC-01-01 Khách đăng ký, xác minh email rồi đăng nhập vào trang bắt đầu Học viên', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Nguyễn Văn An', 'email' => 'an.nguyen@edunest.test',
        'password' => 'MatKhau123', 'password_confirmation' => 'MatKhau123',
    ])->assertSessionHasNoErrors();

    $an = User::query()->where('email', 'an.nguyen@edunest.test')->sole();
    expect($an->status)->toBe(AccountStatus::HoatDong)
        ->and(vaiCua($an))->toBe(['HOC_VIEN'])
        ->and($an->email_verified_at)->toBeNull();
    Notification::assertSentToTimes($an, VerifyEmail::class, 1);

    $url = null;
    Notification::assertSentTo($an, VerifyEmail::class, function (VerifyEmail $n) use ($an, &$url) {
        $url = $n->toMail($an)->actionUrl;

        return true;
    });
    $this->get($url)->assertRedirect();
    expect($an->fresh()->email_verified_at)->not->toBeNull();

    dangXuat();
    dangNhap('an.nguyen@edunest.test', 'MatKhau123')->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect(route('student.dashboard'));
    $this->get(route('student.dashboard'))->assertOk();
});

it('AC-01-02 Khách gửi đăng ký thiếu họ tên', function () {
    Notification::fake();
    $this->post(route('register.store'), [
        'name' => '', 'email' => 'an.nguyen@edunest.test', 'password' => 'MatKhau123', 'password_confirmation' => 'MatKhau123',
    ])->assertSessionHasErrors('name');

    expect(User::query()->count())->toBe(0);
    Notification::assertNothingSent();
    $this->assertGuest();
});

it('AC-01-03 Khách đăng ký với email "AN.NGUYEN@EduNest.test"', function () {
    taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);

    $this->post(route('register.store'), [
        'name' => 'An Hai', 'email' => 'AN.NGUYEN@EduNest.test', 'password' => 'MatKhau123', 'password_confirmation' => 'MatKhau123',
    ])->assertSessionHasErrors('email');

    expect(User::query()->whereRaw('LOWER(email) = ?', ['an.nguyen@edunest.test'])->count())->toBe(1);
});

it('AC-01-04 Khách gửi đăng ký kèm tham số vai = Giáo vụ', function () {
    $this->post(route('register.store'), [
        'name' => 'Nguyễn Văn An', 'email' => 'an.nguyen@edunest.test', 'password' => 'MatKhau123',
        'password_confirmation' => 'MatKhau123', 'role' => 'GIAO_VU', 'roles' => ['GIAO_VU', 'QUAN_TRI'],
    ])->assertSessionHasNoErrors();

    expect(vaiCua(User::query()->where('email', 'an.nguyen@edunest.test')->sole()))->toBe(['HOC_VIEN']);
});

it('AC-01-05 Đăng nhập với an.nguyen@edunest.test và mật khẩu sai; đăng nhập với nobody@edunest.test', function () {
    taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);

    $sai = dangNhap('an.nguyen@edunest.test', 'saimatkhau')->assertSessionHasErrors('email');
    $msg1 = session('errors')->first('email');
    $khong = dangNhap('nobody@edunest.test', 'saimatkhau')->assertSessionHasErrors('email');
    $msg2 = session('errors')->first('email');

    expect($msg1)->toBe('Email hoặc mật khẩu không đúng.')->and($msg2)->toBe($msg1);
    $this->assertGuest();
});

it('AC-01-06 Cặp sai 5 lần trong cửa sổ chưa bị chặn; lần thứ 5 đúng mật khẩu thì đăng nhập được', function () {
    taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);
    taiKhoan('binh@edunest.test', RoleCode::HocVien);

    foreach (['03:00:00', '03:00:10', '03:00:20', '03:00:30', '03:00:40'] as $t) {
        $this->travelTo(Carbon::parse("2026-11-23 {$t}"));
        dangNhap('an.nguyen@edunest.test', 'sai', '10.0.0.5')->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
    }
    $this->assertGuest();

    foreach (['03:00:00', '03:00:10', '03:00:20', '03:00:30'] as $t) {
        $this->travelTo(Carbon::parse("2026-11-23 {$t}"));
        dangNhap('binh@edunest.test', 'sai', '10.0.0.6')->assertSessionHasErrors('email');
    }
    $this->travelTo(Carbon::parse('2026-11-23 03:00:40'));
    dangNhap('binh@edunest.test', UserFactory::PASSWORD, '10.0.0.6')->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});

it('AC-01-07 Sau 5 lần sai: bị chặn tới đủ 60 giây kể từ lần sai đầu; IP khác không ảnh hưởng', function () {
    taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);
    foreach (['03:00:00', '03:00:10', '03:00:20', '03:00:30', '03:00:40'] as $t) {
        $this->travelTo(Carbon::parse("2026-11-23 {$t}"));
        dangNhap('an.nguyen@edunest.test', 'sai', '10.0.0.5');
    }
    $dung = ['email' => 'an.nguyen@edunest.test', 'password' => UserFactory::PASSWORD];

    $this->travelTo(Carbon::parse('2026-11-23 03:00:41'));
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])->postJson(route('login.store'), $dung)->assertStatus(429);
    $this->assertGuest();

    $this->travelTo(Carbon::parse('2026-11-23 03:00:45'));
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->postJson(route('login.store'), $dung)->assertOk();
    $this->assertAuthenticated();
    dangXuat();

    $this->travelTo(Carbon::parse('2026-11-23 03:00:59'));
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])->postJson(route('login.store'), $dung)->assertStatus(429);
    $this->assertGuest();

    $this->travelTo(Carbon::parse('2026-11-23 03:01:00'));
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])->postJson(route('login.store'), $dung)->assertOk();
    $this->assertAuthenticated();
});

it('AC-01-08 An đăng nhập với mật khẩu đúng khi tài khoản BI_KHOA', function () {
    User::factory()->locked()->student()->create(['email' => 'an.nguyen@edunest.test']);

    dangNhap('an.nguyen@edunest.test')->assertSessionHasErrors(['email' => 'Tài khoản của bạn đã bị khóa.']);
    $this->assertGuest();
});

it('AC-01-09 Lan tạo tài khoản Giáo viên Hóa; Hóa phải đổi mật khẩu trước khi vào màn khác', function () {
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $this->signIn($lan)->post(route('academic.teachers.store'), [
        'name' => 'Trần Minh Hóa', 'email' => 'hoa.tran@edunest.test', 'password' => 'TamThoi123',
        'bio' => '10 năm dạy Hóa',
    ])->assertSessionHasNoErrors();

    $hoa = User::query()->where('email', 'hoa.tran@edunest.test')->sole();
    expect($hoa->status)->toBe(AccountStatus::HoatDong)
        ->and(vaiCua($hoa))->toBe(['GIAO_VIEN'])
        ->and($hoa->must_change_password)->toBeTrue();

    dangXuat();
    dangNhap('hoa.tran@edunest.test', 'TamThoi123')->assertRedirect('/dashboard');
    // "Khóa tôi soạn" có từ P1; P0 kiểm trên trang bắt đầu Giáo viên và hồ sơ giáo viên.
    $this->get(route('teacher.dashboard'))->assertRedirect(route('account.password.required'));
    $this->get(route('teacher.profile.edit'))->assertRedirect(route('account.password.required'));

    $this->put(route('account.password.required.update'), [
        'current_password' => 'TamThoi123', 'password' => MAT_KHAU_MOI, 'password_confirmation' => MAT_KHAU_MOI,
    ])->assertSessionHasNoErrors();
    expect($hoa->fresh()->must_change_password)->toBeFalse();
    $this->get(route('teacher.dashboard'))->assertOk();
});

it('AC-01-10 Quang tạo Giáo vụ Minh; Minh đổi mật khẩu, thiết lập 2FA rồi vào trang Giáo vụ', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $this->signIn($quang)->post(route('admin.users.store'), [
        'name' => 'Ngô Minh', 'email' => 'minh@edunest.test', 'password' => 'TamThoi123', 'role' => 'GIAO_VU',
    ])->assertSessionHasNoErrors();
    $minh = User::query()->where('email', 'minh@edunest.test')->sole();
    expect($minh->status)->toBe(AccountStatus::HoatDong)->and(vaiCua($minh))->toBe(['GIAO_VU']);

    dangXuat();
    dangNhap('minh@edunest.test', 'TamThoi123')->assertRedirect('/dashboard');
    $this->put(route('account.password.required.update'), [
        'current_password' => 'TamThoi123', 'password' => MAT_KHAU_MOI, 'password_confirmation' => MAT_KHAU_MOI,
    ])->assertSessionHasNoErrors();

    // Chưa thiết lập 2FA: mọi màn khác dẫn tới thiết lập.
    $this->get('/dashboard')->assertRedirect(route('account.security.edit'));
    $this->get(route('academic.dashboard'))->assertRedirect(route('account.security.edit'));
    $this->get(route('account.profile.edit'))->assertRedirect(route('account.security.edit'));
    $this->get(route('account.security.edit'))->assertOk();

    thietLap2fa($minh);
    $this->getJson(route('two-factor.recovery-codes'))->assertOk()->assertJsonCount(8);
    $this->get('/dashboard')->assertRedirect(route('academic.dashboard'));
    $this->get(route('academic.dashboard'))->assertOk();
});

it('AC-01-11 Lan nhập mật khẩu đúng; nhập mã 2FA sai; nhập mã 2FA đúng', function () {
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);

    dangNhap('lan.pham@edunest.test')->assertRedirect(route('two-factor.login'));
    $this->assertGuest();

    $this->post(route('two-factor.login.store'), ['code' => '000000'])->assertSessionHasErrors('code');
    $this->assertGuest();

    $this->post(route('two-factor.login.store'), ['code' => maOtp($lan)])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($lan);
    $this->get(route('academic.dashboard'))->assertOk();
});

it('AC-01-12 Quang nhập mật khẩu đúng rồi dùng mã khôi phục', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);

    dangNhap('quang@edunest.test')->assertRedirect(route('two-factor.login'));
    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($quang);
    expect($quang->fresh()->recoveryCodes())->not->toContain('recovery-code-1');
});

it('AC-01-13 Quang đặt lại 2FA cho Lan; Lan đăng nhập lần kế tiếp phải thiết lập lại', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $maCu = maOtp($lan);

    $this->signIn($quang)->delete(route('admin.users.two-factor.destroy', $lan))->assertSessionHasNoErrors();
    $lan->refresh();
    expect($lan->two_factor_secret)->toBeNull()->and($lan->two_factor_recovery_codes)->toBeNull()
        ->and($lan->two_factor_confirmed_at)->toBeNull();

    dangXuat();
    // Không còn bước mã thứ hai: mã/mã khôi phục cũ không dùng được; vào màn nào cũng bị dẫn tới thiết lập.
    dangNhap('lan.pham@edunest.test')->assertRedirect('/dashboard');
    $this->post(route('two-factor.login.store'), ['code' => $maCu])->assertRedirect();
    $this->get(route('academic.dashboard'))->assertRedirect(route('account.security.edit'));
    $this->get(route('account.profile.edit'))->assertRedirect(route('account.security.edit'));
});

it('AC-01-14 An quên mật khẩu, đặt mật khẩu mới; mật khẩu cũ bị từ chối, mới đăng nhập được', function () {
    Notification::fake();
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);

    $this->post(route('password.email'), ['email' => 'an.nguyen@edunest.test'])->assertSessionHasNoErrors();
    Notification::assertSentToTimes($an, ResetPassword::class, 1);
    $token = null;
    Notification::assertSentTo($an, ResetPassword::class, function (ResetPassword $n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $this->post(route('password.update'), [
        'token' => $token, 'email' => 'an.nguyen@edunest.test', 'password' => MAT_KHAU_MOI, 'password_confirmation' => MAT_KHAU_MOI,
    ])->assertSessionHasNoErrors();

    dangNhap('an.nguyen@edunest.test', UserFactory::PASSWORD)->assertSessionHasErrors('email');
    $this->assertGuest();
    dangNhap('an.nguyen@edunest.test', MAT_KHAU_MOI)->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($an);
});

it('AC-01-15 Quang khóa An; phiên cũ của An và đăng nhập mới bị từ chối', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);
    $theHeCu = $an->auth_generation;
    DB::table('sessions')->insert(['id' => 'phien-an-1', 'user_id' => $an->id, 'payload' => '', 'last_activity' => now()->timestamp]);

    $this->signIn($quang)->patch(route('admin.users.lock', $an))->assertSessionHasNoErrors();
    expect($an->fresh()->status)->toBe(AccountStatus::BiKhoa)
        ->and(DB::table('sessions')->where('user_id', $an->id)->count())->toBe(0);

    $this->travelTo(Carbon::parse('2026-11-23 03:00:01'));
    phienCu($an, $theHeCu);
    $this->get(route('student.dashboard'))->assertForbidden();
    $this->assertGuest();

    $this->travelTo(Carbon::parse('2026-11-23 03:00:30'));
    dangNhap('an.nguyen@edunest.test')->assertSessionHasErrors(['email' => 'Tài khoản của bạn đã bị khóa.']);
    $this->assertGuest();
    // Ghi danh giữ nguyên: kiểm khi có bảng enrollments (P3).
});

it('AC-01-16 Quang chọn "Khóa" rồi bấm "Hủy" trong hộp xác nhận: An vẫn hoạt động, phiên còn hiệu lực', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);

    // Hộp xác nhận ở phía trình duyệt: "Hủy" không gửi yêu cầu khóa (luồng Browser ở tests/Browser khi bật plugin).
    $this->signIn($quang)->get(route('admin.users.show', $an))->assertOk()
        ->assertInertia(fn ($p) => $p->where('can.lock', true));

    phienCu($an, $an->auth_generation);
    $this->get(route('student.dashboard'))->assertOk();
    expect($an->fresh()->status)->toBe(AccountStatus::HoatDong);
});

it('AC-01-19 Quang mở khóa; An đăng nhập bằng email và mật khẩu cũ', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $an = User::factory()->locked()->student()->create(['email' => 'an.nguyen@edunest.test']);

    $this->signIn($quang)->patch(route('admin.users.unlock', $an))->assertSessionHasNoErrors();
    expect($an->fresh()->status)->toBe(AccountStatus::HoatDong);

    dangXuat();
    dangNhap('an.nguyen@edunest.test')->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($an);
});

it('AC-01-20 Hóa được gán thêm Giáo vụ: quyền Giáo vụ chờ 2FA, quyền Giáo viên vẫn dùng; gỡ thì mất ở yêu cầu kế tiếp', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $hoa = taiKhoan('hoa.tran@edunest.test', RoleCode::GiaoVien);
    $theHe = $hoa->auth_generation;

    app(ChangeAccountRoles::class)->assign($quang, $hoa, RoleCode::GiaoVu);

    // "Duyệt khóa" có từ P1; P0 kiểm quyền Giáo vụ trên các màn Giáo vụ.
    phienCu($hoa, $theHe);
    $this->get(route('academic.dashboard'))->assertRedirect(route('account.security.edit'));
    $this->post(route('academic.teachers.store'), ['name' => 'X', 'email' => 'x@edunest.test', 'password' => 'TamThoi123'])->assertForbidden();
    $this->get(route('teacher.dashboard'))->assertOk();

    thietLap2fa($hoa);
    $this->get(route('academic.dashboard'))->assertOk();
    $this->get(route('teacher.dashboard'))->assertOk();

    app(ChangeAccountRoles::class)->remove($quang, $hoa->fresh(), RoleCode::GiaoVu);
    phienCu($hoa->fresh(), $theHe);
    $this->get(route('academic.dashboard'))->assertForbidden();
    $this->get(route('teacher.dashboard'))->assertOk();
});

it('AC-01-21 An đổi họ tên thành "Nguyễn Văn Anh" và avatar mới', function () {
    Storage::fake('public');
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);
    $binh = taiKhoan('binh@edunest.test', RoleCode::HocVien);

    $this->signIn($an)->patch(route('account.profile.update'), [
        'name' => 'Nguyễn Văn Anh', 'avatar' => UploadedFile::fake()->image('anh.jpg'),
    ])->assertSessionHasNoErrors();

    $an->refresh();
    expect($an->name)->toBe('Nguyễn Văn Anh')->and($an->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists((string) $an->avatar_path);
    expect($binh->fresh()->only(['name', 'avatar_path']))->toBe($binh->only(['name', 'avatar_path']));
});

it('AC-01-23 An chưa xác minh email vẫn đăng nhập, xem phần công khai và thấy nhắc xác minh', function () {
    User::factory()->unverified()->student()->create(['email' => 'an.nguyen@edunest.test']);

    dangNhap('an.nguyen@edunest.test')->assertSessionHasNoErrors();
    $this->get(route('student.dashboard'))->assertOk()
        ->assertInertia(fn ($p) => $p->where('needsEmailVerification', true));
    $this->get(route('home'))->assertOk();
    // Mua khóa (P3) và đổi mã (P5) gắn middleware `verified`: kiểm ở QT-07, QT-14.
});

it('AC-01-26 Học viên An gọi tạo tài khoản Giáo viên', function () {
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);
    $truoc = User::query()->count();

    $this->signIn($an)->post(route('academic.teachers.store'), [
        'name' => 'Hóa', 'email' => 'hoa.tran@edunest.test', 'password' => 'TamThoi123',
    ])->assertForbidden();
    expect(User::query()->count())->toBe($truoc);
});

it('AC-01-27 Lan gọi tạo tài khoản vai Giáo vụ; tạo tài khoản vai Quản trị', function () {
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $truoc = User::query()->count();

    foreach (['GIAO_VU', 'QUAN_TRI'] as $vai) {
        $this->signIn($lan)->post(route('admin.users.store'), [
            'name' => 'Mới', 'email' => strtolower($vai).'@edunest.test', 'password' => 'TamThoi123', 'role' => $vai,
        ])->assertForbidden();
    }
    expect(User::query()->count())->toBe($truoc);
});

it('AC-01-28 Lan gọi khóa tài khoản An', function () {
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);

    $this->signIn($lan)->patch(route('admin.users.lock', $an))->assertForbidden();
    expect($an->fresh()->status)->toBe(AccountStatus::HoatDong);
});

it('AC-01-29 Hóa gọi gán vai Giáo vụ cho chính mình', function () {
    $hoa = taiKhoan('hoa.tran@edunest.test', RoleCode::GiaoVien);

    $this->signIn($hoa)->post(route('admin.users.roles.store', $hoa), ['role' => 'GIAO_VU'])->assertForbidden();
    expect(vaiCua($hoa))->toBe(['GIAO_VIEN']);
});

it('AC-01-30 Lan gọi gán vai Giáo vụ cho Hóa', function () {
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $hoa = taiKhoan('hoa.tran@edunest.test', RoleCode::GiaoVien);

    $this->signIn($lan)->post(route('admin.users.roles.store', $hoa), ['role' => 'GIAO_VU'])->assertForbidden();
    expect(vaiCua($hoa))->toBe(['GIAO_VIEN']);
});

it('AC-01-31 Lan gọi đặt lại 2FA của Minh', function () {
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $minh = taiKhoan('minh@edunest.test', RoleCode::GiaoVu);
    $truoc = $minh->two_factor_secret;

    $this->signIn($lan)->delete(route('admin.users.two-factor.destroy', $minh))->assertForbidden();
    expect($minh->fresh()->two_factor_secret)->toBe($truoc);
});

it('AC-01-32 Quang gọi đặt lại 2FA của Quản trị Dũng qua giao diện', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $dung = taiKhoan('dung@edunest.test', RoleCode::QuanTri);
    $truoc = $dung->two_factor_secret;

    $this->signIn($quang)->delete(route('admin.users.two-factor.destroy', $dung))->assertForbidden();
    expect($dung->fresh()->two_factor_secret)->toBe($truoc)->and($dung->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('AC-01-33 An gọi sửa hồ sơ, avatar của Bình', function () {
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);
    $binh = taiKhoan('binh@edunest.test', RoleCode::HocVien);

    $this->signIn($an)->patch(route('account.profile.update'), ['user_id' => $binh->id, 'name' => 'Đổi bậy'])->assertForbidden();
    expect($binh->fresh()->name)->toBe($binh->name)->and($an->fresh()->name)->toBe($an->name);
});

it('AC-01-34 Hóa vừa được gán Quản trị mở màn Cấu hình bằng phiên đang mở; sau 2FA mở được', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $hoa = taiKhoan('hoa.tran@edunest.test', RoleCode::GiaoVien);
    app(ChangeAccountRoles::class)->assign($quang, $hoa, RoleCode::QuanTri);

    phienCu($hoa, $hoa->auth_generation);
    $this->get(route('admin.settings.edit'))->assertRedirect(route('account.security.edit'));
    $this->getJson(route('admin.settings.edit'))->assertForbidden();

    thietLap2fa($hoa);
    $this->get(route('admin.settings.edit'))->assertOk();
});

it('AC-01-35 Quang tạo Giáo vụ với email "LAN.PHAM@EduNest.test" đã có', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $truoc = User::query()->count();

    $this->signIn($quang)->post(route('admin.users.store'), [
        'name' => 'Lan Hai', 'email' => 'LAN.PHAM@EduNest.test', 'password' => 'TamThoi123', 'role' => 'GIAO_VU',
    ])->assertSessionHasErrors('email');
    expect(User::query()->count())->toBe($truoc);
});

it('AC-01-36 Lan gọi mở khóa An', function () {
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $an = User::factory()->locked()->student()->create(['email' => 'an.nguyen@edunest.test']);

    $this->signIn($lan)->patch(route('admin.users.unlock', $an))->assertForbidden();
    expect($an->fresh()->status)->toBe(AccountStatus::BiKhoa);
});

it('AC-01-37 Bình gọi gỡ vai Giáo vụ của Hóa; Lan gọi gỡ vai Giáo viên của Hóa', function () {
    $hoa = taiKhoan('hoa.tran@edunest.test', RoleCode::GiaoVien, RoleCode::GiaoVu);
    $binh = taiKhoan('binh@edunest.test', RoleCode::GiaoVien);
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);

    $this->signIn($binh)->delete(route('admin.users.roles.destroy', [$hoa, 'GIAO_VU']))->assertForbidden();
    $this->signIn($lan)->delete(route('admin.users.roles.destroy', [$hoa, 'GIAO_VIEN']))->assertForbidden();
    expect(vaiCua($hoa))->toEqualCanonicalizing(['GIAO_VIEN', 'GIAO_VU']);
});

it('AC-01-38 Mật khẩu 7 ký tự bị từ chối, 8 ký tự được (đăng ký, đổi bắt buộc, đặt lại)', function () {
    $this->post(route('register.store'), [
        'name' => 'Mới', 'email' => 'moi@edunest.test', 'password' => 'Abcdef1', 'password_confirmation' => 'Abcdef1',
    ])->assertSessionHasErrors('password');
    expect(User::query()->where('email', 'moi@edunest.test')->exists())->toBeFalse();

    $this->post(route('register.store'), [
        'name' => 'Mới', 'email' => 'moi@edunest.test', 'password' => 'Abcdefg1', 'password_confirmation' => 'Abcdefg1',
    ])->assertSessionHasNoErrors();
    expect(User::query()->where('email', 'moi@edunest.test')->exists())->toBeTrue();
    dangXuat();

    $hoa = User::factory()->mustChangePassword()->teacher()->create(['email' => 'hoa.tran@edunest.test']);
    $this->signIn($hoa)->put(route('account.password.required.update'), [
        'current_password' => UserFactory::PASSWORD, 'password' => 'Abcdef1', 'password_confirmation' => 'Abcdef1',
    ])->assertSessionHasErrors('password');
    $this->put(route('account.password.required.update'), [
        'current_password' => UserFactory::PASSWORD, 'password' => 'Abcdefg1', 'password_confirmation' => 'Abcdefg1',
    ])->assertSessionHasNoErrors();
    dangXuat();

    Notification::fake();
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);
    $this->post(route('password.email'), ['email' => $an->email]);
    $token = null;
    Notification::assertSentTo($an, ResetPassword::class, function (ResetPassword $n) use (&$token) {
        $token = $n->token;

        return true;
    });
    $this->post(route('password.update'), ['token' => $token, 'email' => $an->email, 'password' => 'Abcdef1', 'password_confirmation' => 'Abcdef1'])
        ->assertSessionHasErrors('password');
    $this->post(route('password.update'), ['token' => $token, 'email' => $an->email, 'password' => 'Abcdefg1', 'password_confirmation' => 'Abcdefg1'])
        ->assertSessionHasNoErrors();
});

it('AC-01-39 Liên kết xác minh/đặt lại hiệu lực 60 phút và dùng một lần', function () {
    Notification::fake();
    $an = User::factory()->unverified()->student()->create(['email' => 'an.nguyen@edunest.test']);
    $binh = User::factory()->unverified()->student()->create(['email' => 'binh@edunest.test']);
    $an->sendEmailVerificationNotification();
    $binh->sendEmailVerificationNotification();
    $lienKet = fn (User $u) => Notification::sent($u, VerifyEmail::class)->first()->toMail($u)->actionUrl;
    $urlAn = $lienKet($an);
    $urlBinh = $lienKet($binh);

    $this->travelTo(Carbon::parse('2026-11-23 03:59:59'));
    $this->signIn($an)->get($urlAn)->assertRedirect();
    expect($an->fresh()->email_verified_at)->not->toBeNull();

    $this->travelTo(Carbon::parse('2026-11-23 04:00:01'));
    $this->signIn($binh)->get($urlBinh)->assertForbidden();
    expect($binh->fresh()->email_verified_at)->toBeNull();
    dangXuat();

    $this->travelTo(Carbon::parse('2026-11-23 05:00:00'));
    $this->post(route('password.email'), ['email' => $an->email]);
    $token = Notification::sent($an, ResetPassword::class)->first()->token;

    $this->travelTo(Carbon::parse('2026-11-23 05:30:00'));
    $this->post(route('password.update'), ['token' => $token, 'email' => $an->email, 'password' => MAT_KHAU_MOI, 'password_confirmation' => MAT_KHAU_MOI])
        ->assertSessionHasNoErrors();

    $this->travelTo(Carbon::parse('2026-11-23 05:40:00'));
    $this->post(route('password.update'), ['token' => $token, 'email' => $an->email, 'password' => 'KhacNua99', 'password_confirmation' => 'KhacNua99'])
        ->assertSessionHasErrors('email');
    dangNhap($an->email, MAT_KHAU_MOI)->assertSessionHasNoErrors();
});

it('AC-01-40 Quên mật khẩu với email có và không có tài khoản cho cùng một phản hồi', function () {
    Notification::fake();
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);

    $this->from(route('password.request'))->post(route('password.email'), ['email' => 'an.nguyen@edunest.test'])
        ->assertRedirect(route('password.request'))->assertSessionHasNoErrors();
    $p1 = session('status');
    $this->from(route('password.request'))->post(route('password.email'), ['email' => 'nobody@edunest.test'])
        ->assertRedirect(route('password.request'))->assertSessionHasNoErrors();
    $p2 = session('status');

    expect($p1)->toBe($p2)->not->toBeNull();
    Notification::assertSentToTimes($an, ResetPassword::class, 1);
    Notification::assertCount(1);
});

it('AC-01-41 Quang gọi khóa chính tài khoản của mình', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    taiKhoan('dung@edunest.test', RoleCode::QuanTri);

    $this->signIn($quang)->patch(route('admin.users.lock', $quang))->assertForbidden();
    expect($quang->fresh()->status)->toBe(AccountStatus::HoatDong);
    $this->signIn($quang)->get(route('admin.dashboard'))->assertOk();
});

it('AC-01-43 Lệnh quản trị trên máy chủ đặt lại 2FA của Quản trị Dũng', function () {
    taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $dung = taiKhoan('dung@edunest.test', RoleCode::QuanTri);

    expect(Artisan::call('edunest:admin:reset-2fa', ['email' => 'dung@edunest.test']))->toBe(0);
    $dung->refresh();
    expect($dung->two_factor_secret)->toBeNull()->and($dung->two_factor_confirmed_at)->toBeNull();

    dangNhap('dung@edunest.test')->assertRedirect('/dashboard');
    $this->get(route('admin.dashboard'))->assertRedirect(route('account.security.edit'));
    // Nhật ký "đặt lại 2FA" với tác nhân LENH_MAY_CHU: từ P1 (audit_logs).
});

it('AC-01-44 Không có đường tạo Quản trị qua đăng ký; Quản trị khởi tạo đổi mật khẩu, thiết lập 2FA rồi vào', function () {
    $this->post(route('register.store'), [
        'name' => 'Giả', 'email' => 'gia@edunest.test', 'password' => 'MatKhau123', 'password_confirmation' => 'MatKhau123', 'role' => 'QUAN_TRI',
    ]);
    expect(vaiCua(User::query()->where('email', 'gia@edunest.test')->sole()))->toBe(['HOC_VIEN']);
    dangXuat();

    expect(Artisan::call('edunest:admin:create', ['email' => 'quang@edunest.test', '--name' => 'Lê Quang', '--password' => 'KhoiTao123']))->toBe(0);
    $quang = User::query()->where('email', 'quang@edunest.test')->sole();

    dangNhap('quang@edunest.test', 'KhoiTao123')->assertRedirect('/dashboard');
    $this->get(route('admin.dashboard'))->assertRedirect(route('account.password.required'));
    $this->put(route('account.password.required.update'), [
        'current_password' => 'KhoiTao123', 'password' => MAT_KHAU_MOI, 'password_confirmation' => MAT_KHAU_MOI,
    ])->assertSessionHasNoErrors();
    $this->get(route('admin.dashboard'))->assertRedirect(route('account.security.edit'));
    thietLap2fa($quang);
    $this->get(route('admin.dashboard'))->assertOk();
});

it('AC-01-45 Email tài khoản được cấp coi là đã xác minh, không gửi email xác minh; không đổi được email', function () {
    Notification::fake();
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $this->signIn($lan)->post(route('academic.teachers.store'), [
        'name' => 'Trần Minh Hóa', 'email' => 'hoa.tran@edunest.test', 'password' => 'TamThoi123',
    ])->assertSessionHasNoErrors();
    $hoa = User::query()->where('email', 'hoa.tran@edunest.test')->sole();
    expect($hoa->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();

    dangXuat();
    dangNhap('hoa.tran@edunest.test', 'TamThoi123');
    $this->put(route('account.password.required.update'), [
        'current_password' => 'TamThoi123', 'password' => MAT_KHAU_MOI, 'password_confirmation' => MAT_KHAU_MOI,
    ])->assertSessionHasNoErrors();
    $this->patch(route('account.profile.update'), ['name' => 'Trần Minh Hóa', 'email' => 'hoa2@edunest.test'])
        ->assertSessionHasErrors('email');
    expect($hoa->fresh()->email)->toBe('hoa.tran@edunest.test');
});

it('AC-01-49 Quang đăng nhập đúng mật khẩu và mã 2FA vào trang bắt đầu Quản trị', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);

    dangNhap('quang@edunest.test')->assertRedirect(route('two-factor.login'));
    $this->post(route('two-factor.login.store'), ['code' => maOtp($quang)])->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect(route('admin.dashboard'));
    $this->get(route('admin.dashboard'))->assertOk()->assertInertia(fn ($p) => $p->component('admin/dashboard'));
    $this->get(route('student.dashboard'))->assertForbidden();
});

it('AC-01-50 Quang đặt lại 2FA của Minh: mọi phiên của Minh bị đăng xuất ngay', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $minh = taiKhoan('minh@edunest.test', RoleCode::GiaoVu);
    $theHe = $minh->auth_generation;
    DB::table('sessions')->insert([
        ['id' => 'minh-may-tinh', 'user_id' => $minh->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'minh-dien-thoai', 'user_id' => $minh->id, 'payload' => '', 'last_activity' => now()->timestamp],
    ]);

    $this->signIn($quang)->delete(route('admin.users.two-factor.destroy', $minh))->assertSessionHasNoErrors();
    expect(DB::table('sessions')->where('user_id', $minh->id)->count())->toBe(0);

    phienCu($minh, $theHe);
    $this->get(route('academic.dashboard'))->assertForbidden();
    dangNhap('minh@edunest.test')->assertRedirect('/dashboard');
    $this->get(route('academic.dashboard'))->assertRedirect(route('account.security.edit'));
});

it('AC-01-51 Hóa có 2FA được gán thêm Giáo vụ: phiên đang mở vào được màn Giáo vụ không cần nhập lại mã', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $hoa = User::factory()->withTwoFactor()->teacher()->create(['email' => 'hoa.tran@edunest.test']);
    $theHe = $hoa->auth_generation;

    app(ChangeAccountRoles::class)->assign($quang, $hoa, RoleCode::GiaoVu);
    phienCu($hoa->fresh(), $theHe);
    // Hàng chờ duyệt có từ P1; P0 kiểm trang Giáo vụ.
    $this->get(route('academic.dashboard'))->assertOk();
});

it('AC-01-52 Quản trị hoạt động duy nhất gỡ vai Quản trị của chính mình', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);

    $this->signIn($quang)->deleteJson(route('admin.users.roles.destroy', [$quang, 'QUAN_TRI']))
        ->assertForbidden()->assertJson(['message' => 'Không thể gỡ vai Quản trị hoạt động cuối.']);
    expect(vaiCua($quang))->toBe(['QUAN_TRI']);
});

it('DT-25 tuần tự: Quang khóa Dũng rồi Dũng khóa Quang: yêu cầu sau bị từ chối, còn đúng 1 Quản trị', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $dung = taiKhoan('dung@edunest.test', RoleCode::QuanTri);

    app(LockAccount::class)->handle($quang, $dung);
    expect(fn () => app(LockAccount::class)->handle($dung->fresh(), $quang))->toThrow(DomainRejection::class);
    expect(User::query()->where('status', 'HOAT_DONG')->count())->toBe(1);
});

it('AC-01-22 X (Giáo viên + Giáo vụ) soạn K1, duyệt K2 được; duyệt K1 của mình bị 403', function () {
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $x = taiKhoan('x@edunest.test', RoleCode::GiaoVien, RoleCode::GiaoVu);
    $khac = taiKhoan('gv.khac@edunest.test', RoleCode::GiaoVien);
    $k1 = KhoaHoc::giao($lan, $x);
    $v1 = KhoaHoc::duDieuKien($k1, 1);
    $k2 = KhoaHoc::giao($lan, $khac, ['title' => 'K2']);
    $v2 = KhoaHoc::duDieuKien($k2, 1);
    KhoaHoc::guiDuyet($v2, $khac);
    $bai = Lesson::query()->where('course_version_id', $v1->id)->first();

    $this->signIn($x)->patchJson(route('teacher.lessons.update', $bai), ['title' => 'X sửa'])->assertRedirect();
    $this->postJson(route('academic.course-versions.approve', $v2))->assertRedirect();
    $this->postJson(route('teacher.course-versions.submit', $v1))->assertRedirect();
    $this->postJson(route('academic.course-versions.approve', $v1))->assertForbidden();

    expect($v2->fresh()->status->value)->toBe('DA_DUYET')->and($v1->fresh()->status->value)->toBe('CHO_DUYET');
})->group('P1');

it('AC-01-24 Tạo tài khoản, khóa, gán vai: mỗi thao tác đúng 1 bản ghi nhật ký', function () {
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);

    $this->signIn($lan)->post(route('academic.teachers.store'), ['name' => 'Hóa', 'email' => 'hoa.tran@edunest.test', 'password' => 'TamThoi123'])->assertSessionHasNoErrors();
    $hoa = User::query()->where('email', 'hoa.tran@edunest.test')->sole();
    $this->signIn($quang)->patch(route('admin.users.lock', $an))->assertSessionHasNoErrors();
    $this->post(route('admin.users.roles.store', $hoa), ['role' => 'GIAO_VU'])->assertSessionHasNoErrors();

    $log = fn (string $a) => AuditLog::query()->where('action', $a)->sole();
    $tao = $log('ACCOUNT_CREATE');
    expect($tao->actor_id)->toBe($lan->id)->and($tao->subject_id)->toBe($hoa->id)->and($tao->old_values)->toBeNull()
        ->and($tao->new_values)->toEqual(['status' => 'HOAT_DONG', 'roles' => ['GIAO_VIEN']])->and($tao->created_at)->not->toBeNull();
    $khoa = $log('ACCOUNT_LOCK');
    expect($khoa->actor_id)->toBe($quang->id)->and($khoa->subject_id)->toBe($an->id)
        ->and($khoa->old_values)->toBe(['status' => 'HOAT_DONG'])->and($khoa->new_values)->toBe(['status' => 'BI_KHOA']);
    $vai = $log('ROLE_ASSIGN');
    expect($vai->subject_id)->toBe($hoa->id)->and($vai->old_values)->toBe(['roles' => ['GIAO_VIEN']])
        ->and($vai->new_values)->toBe(['roles' => ['GIAO_VIEN', 'GIAO_VU']]);
})->group('P1');

it('AC-01-46 Quang đặt lại 2FA của Lan: 1 bản ghi nhật ký, cũ "đã thiết lập", mới "chưa thiết lập"', function () {
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $this->signIn($quang)->delete(route('admin.users.two-factor.destroy', $lan))->assertSessionHasNoErrors();
    $log = AuditLog::query()->where('action', 'TWO_FACTOR_RESET')->sole();
    expect($log->actor_id)->toBe($quang->id)->and($log->subject_id)->toBe($lan->id)
        ->and($log->old_values)->toBe(['two_factor' => 'DA_THIET_LAP'])->and($log->new_values)->toBe(['two_factor' => 'CHUA_THIET_LAP']);
})->group('P1');
