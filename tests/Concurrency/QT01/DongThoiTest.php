<?php

/*
| Đồng thời thật trên MySQL (07 §5): hai tiến trình, dữ liệu đã commit, ép đủ hai thứ tự.
| DT-25 (AC-01-42), DT-26 (CreateAuthenticatedSession × LockAccount).
*/

use App\Actions\M01\LockAccount;
use App\Actions\M01\UnlockAccount;
use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Support\HaiTienTrinh;

uses(DatabaseTruncation::class);
pest()->group('P0', 'concurrency');

it('AC-01-42 Quang khóa Dũng và Dũng khóa Quang cùng lúc: đúng một yêu cầu thành công', function (string $truoc) {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $dung = taiKhoan('dung@edunest.test', RoleCode::QuanTri);
    [$x, $y] = $truoc === 'Quang trước' ? [$quang, $dung] : [$dung, $quang];

    $kq = HaiTienTrinh::chay(
        ['lock-account', ['actor' => $x->id, 'target' => $y->id]], 'lock-account.locked',
        ['lock-account', ['actor' => $y->id, 'target' => $x->id]],
    );

    expect($kq['a']['ok'])->toBeTrue()
        ->and($kq['b']['ok'])->toBeFalse()
        ->and($kq['b']['error'])->toBe('Không thể khóa Quản trị hoạt động cuối cùng.')
        ->and($y->fresh()->status)->toBe(AccountStatus::BiKhoa)
        ->and($x->fresh()->status)->toBe(AccountStatus::HoatDong)
        ->and(User::query()->where('status', 'HOAT_DONG')->count())->toBe(1)
        ->and($y->fresh()->auth_generation)->toBe(1);
})->with(['Quang trước', 'Dũng trước']);

it('DT-26 Tạo phiên trước rồi khóa: phiên đã ghi mang thế hệ cũ, bị thu hồi', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);

    $kq = HaiTienTrinh::chay(
        ['create-session', ['user' => $an->id]], 'create-session.locked',
        ['lock-account', ['actor' => $quang->id, 'target' => $an->id]],
    );

    expect($kq['a']['gen'])->toBe(0)->and($kq['b']['ok'])->toBeTrue();
    // Middleware account.active so thế hệ phiên (0) với dòng users (1) → phiên vô hiệu.
    expect($an->fresh()->auth_generation)->toBe(1)->and($an->fresh()->status)->toBe(AccountStatus::BiKhoa);
});

it('DT-26 Khóa trước rồi tạo phiên: điểm cấp phiên từ chối', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);

    $kq = HaiTienTrinh::chay(
        ['lock-account', ['actor' => $quang->id, 'target' => $an->id]], 'lock-account.locked',
        ['create-session', ['user' => $an->id]],
    );

    expect($kq['a']['ok'])->toBeTrue()->and($kq['b']['gen'])->toBe(-1);
});

it('DT-26 Bước đăng nhập dở mang thế hệ cũ: khóa rồi mở lại trước khi hoàn tất vẫn bị chặn', function () {
    $quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);

    app(LockAccount::class)->handle($quang, $an);
    app(UnlockAccount::class)->handle($quang, $an->fresh());

    $kq = HaiTienTrinh::chay(
        ['create-session', ['user' => $an->id, 'pending' => 0]], 'create-session.locked',
        ['create-session', ['user' => $an->id, 'pending' => 1]],
    );
    expect($kq['a']['gen'])->toBe(-1)->and($kq['b']['gen'])->toBe(1);
});
