<?php

/*
| QT-12 Nhật ký — phần ghi ở P1 (AC-12-25, AC-12-26); phần xem ở P4.
*/

use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M03\ReassignCourseAuthor;
use App\Actions\M03\UpdateCoursePricing;
use App\Actions\M05\CreateDraftVersion;
use App\Enums\M01\RoleCode;
use App\Models\M11\AuditLog;
use Illuminate\Support\Carbon;
use Tests\Support\KhoaHoc;

pest()->group('P1');

afterEach(fn () => Carbon::setTestNow());

it('AC-12-25 Công bố bản 2: một bản ghi người làm, thời điểm, phiên bản 2, cũ DA_DUYET, mới DA_CONG_BO', function () {
    $g = taiKhoan('g@edunest.test', RoleCode::GiaoVu);
    $b = taiKhoan('b@edunest.test', RoleCode::GiaoVu);
    $t = taiKhoan('t@edunest.test', RoleCode::GiaoVien);
    $k = KhoaHoc::daCongBo($g, $t, $b);
    $v2 = app(CreateDraftVersion::class)->handle($t, $k)['version'];
    KhoaHoc::guiDuyet($v2, $t);
    KhoaHoc::duyet($v2, $b);

    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00', 'Asia/Ho_Chi_Minh'));
    $this->signIn($b)->post(route('academic.course-versions.publish', $v2))->assertRedirect();

    $log = AuditLog::query()->where('action', 'VERSION_PUBLISH')->where('subject_id', $v2->id)->sole();
    expect($log->actor_id)->toBe($b->id)->and($log->subject_type)->toBe('course_version')
        ->and($log->created_at->equalTo(Carbon::parse('2026-11-18 10:00:00', 'Asia/Ho_Chi_Minh')))->toBeTrue()
        ->and($log->old_values['status'])->toBe('DA_DUYET')->and($log->new_values['status'])->toBe('DA_CONG_BO')
        ->and($log->reason)->toBeNull();
});

it('AC-12-26 Chuỗi thao tác khóa ghi đúng mỗi việc một bản ghi theo thứ tự thời gian', function () {
    $g = taiKhoan('g@edunest.test', RoleCode::GiaoVu);
    $b = taiKhoan('b@edunest.test', RoleCode::GiaoVu);
    $t = taiKhoan('t@edunest.test', RoleCode::GiaoVien);
    $u = taiKhoan('u@edunest.test', RoleCode::GiaoVien);
    $o = fn (string $hm) => Carbon::setTestNow(Carbon::parse("2026-11-18 {$hm}", 'Asia/Ho_Chi_Minh'));

    $o('08:00:00');
    $k = KhoaHoc::giao($g, $t);
    $o('08:05:00');
    $v = KhoaHoc::duDieuKien($k, 2);
    $o('08:10:00');
    KhoaHoc::guiDuyet($v, $t);
    $o('08:20:00');
    KhoaHoc::duyet($v, $b);
    $o('08:25:00');
    KhoaHoc::congBo($v, $b);
    $o('09:00:00');
    app(ChangeSaleStatus::class)->start($g, $k->fresh());
    $o('09:10:00');
    app(ChangeSaleStatus::class)->stop($g, $k->fresh());
    $o('09:20:00');
    app(UpdateCoursePricing::class)->handle($g, $k->fresh(), 449000, null, null);
    $o('09:30:00');
    app(ReassignCourseAuthor::class)->handle($g, $k->fresh(), $u->id);

    $rows = AuditLog::query()->orderBy('created_at')->orderBy('id')->get(['action', 'actor_id', 'old_values', 'new_values']);
    expect($rows->pluck('action')->all())->toBe([
        'COURSE_ASSIGN', 'VERSION_SUBMIT', 'VERSION_APPROVE', 'VERSION_PUBLISH',
        'COURSE_SELL_START', 'COURSE_SELL_STOP', 'COURSE_PRICE', 'COURSE_REASSIGN',
    ]);
    $gia = $rows->firstWhere('action', 'COURSE_PRICE');
    expect($gia->actor_id)->toBe($g->id)->and($gia->old_values['price'])->toBe(499000)->and($gia->new_values['price'])->toBe(449000);
    $giaoLai = $rows->firstWhere('action', 'COURSE_REASSIGN');
    expect($giaoLai->old_values['author_id'])->toBe($t->id)->and($giaoLai->new_values['author_id'])->toBe($u->id);
});
