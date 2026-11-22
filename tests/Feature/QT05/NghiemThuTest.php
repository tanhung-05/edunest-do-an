<?php

/*
| QT-05 Cập nhật khóa đang bán, ngừng bán, bán lại — nhóm P1 của 07a.
*/

use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M03\ReassignCourseAuthor;
use App\Actions\M04\QuestionBankActions;
use App\Actions\M05\CreateDraftVersion;
use App\Actions\M05\EditDraftContent;
use App\Enums\M01\RoleCode;
use App\Enums\M03\SaleStatus;
use App\Enums\M05\CourseVersionStatus;
use App\Enums\M05\LessonType;
use App\Models\M03\Course;
use App\Models\M04\Question;
use App\Models\M04\QuizQuestion;
use App\Models\M05\CourseReview;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\M11\AuditLog;
use App\Models\User;
use App\Support\M03\ReviewerIndependence;
use Tests\Support\KhoaHoc;

pest()->group('P1');

beforeEach(function () {
    $this->lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $this->minh = taiKhoan('minh@edunest.test', RoleCode::GiaoVu);
    $this->a = taiKhoan('hoa.tran@edunest.test', RoleCode::GiaoVien);
    $this->b = taiKhoan('binh.gv@edunest.test', RoleCode::GiaoVien);
    $this->quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
});

function cb(User $u, string $method, string $url, array $data = [])
{
    return test()->signIn($u)->json($method, $url, $data);
}

/** K đã công bố bản 1 với 12 bài; bán nếu cần. */
function khoaCongBo($t, bool $ban = true, ?User $author = null): Course
{
    $author ??= $t->a;
    $k = KhoaHoc::giao($t->lan, $author);
    $v = KhoaHoc::duDieuKien($k, 12);
    KhoaHoc::guiDuyet($v, $author);
    KhoaHoc::duyet($v, $t->minh);
    KhoaHoc::congBo($v, $t->lan);
    if ($ban) {
        app(ChangeSaleStatus::class)->start($t->lan, $k->fresh());
    }

    return $k->fresh();
}

function banSo(Course $k, int $no): CourseVersion
{
    return CourseVersion::query()->where('course_id', $k->id)->where('version_no', $no)->sole();
}

it('AC-05-01 Tạo bản nháp mới: bản 2 NHAP giữ khóa bài, bản 1 vẫn DA_CONG_BO, K vẫn DANG_BAN', function () {
    $k = khoaCongBo($this);
    cb($this->a, 'POST', route('teacher.courses.versions.store', $k))->assertRedirect();
    $v1 = banSo($k, 1);
    $v2 = banSo($k, 2);
    $keys = fn (CourseVersion $v) => Lesson::query()->where('course_version_id', $v->id)->orderBy('position')->pluck('lesson_key')->all();
    expect($v2->status)->toBe(CourseVersionStatus::Nhap)->and($keys($v2))->toHaveCount(12)->toBe($keys($v1))
        ->and($v1->status)->toBe(CourseVersionStatus::DaCongBo)->and($k->fresh()->sale_status)->toBe(SaleStatus::DangBan);
});

it('AC-05-02 Sửa, thêm bài 13, duyệt, công bố: bản 2 DA_CONG_BO, bản 1 DA_THAY_THE, khóa bài đúng', function () {
    $k = khoaCongBo($this);
    $v2 = app(CreateDraftVersion::class)->handle($this->a, $k)['version'];
    $v1 = banSo($k, 1);
    $este = Lesson::query()->where('course_version_id', $v2->id)->where('title', 'Bài 1')->sole();
    cb($this->a, 'PATCH', route('teacher.lessons.update', $este), ['title' => 'Este'])->assertRedirect();
    $s = $v2->sections()->first();
    cb($this->a, 'POST', route('teacher.sections.lessons.store', $s), ['title' => 'Bài 13', 'type' => 'BAI_DOC'])->assertRedirect();
    $b13 = Lesson::query()->where('course_version_id', $v2->id)->where('title', 'Bài 13')->sole();
    cb($this->a, 'PATCH', route('teacher.lessons.update', $b13), ['content' => '<p>Mới</p>'])->assertRedirect();
    cb($this->a, 'POST', route('teacher.course-versions.submit', $v2))->assertRedirect();
    cb($this->minh, 'POST', route('academic.course-versions.approve', $v2))->assertRedirect();
    cb($this->lan, 'POST', route('academic.course-versions.publish', $v2))->assertRedirect();

    $b1v1 = Lesson::query()->where('course_version_id', $v1->id)->where('title', 'Bài 1')->sole();
    expect($v2->fresh()->status)->toBe(CourseVersionStatus::DaCongBo)->and($v1->fresh()->status)->toBe(CourseVersionStatus::DaThayThe)
        ->and(CourseVersion::query()->where('course_id', $k->id)->where('status', 'DA_CONG_BO')->count())->toBe(1)
        ->and($este->fresh()->lesson_key)->toBe($b1v1->lesson_key)
        ->and(Lesson::query()->where('lesson_key', $b13->lesson_key)->count())->toBe(1)
        ->and($k->fresh()->sale_status)->toBe(SaleStatus::DangBan)
        ->and(AuditLog::query()->where('action', 'VERSION_PUBLISH')->where('subject_id', $v2->id)->count())->toBe(1);
});

it('AC-05-06 Tạo thêm bản nháp khi bản 2 đang NHAP hoặc DA_DUYET: bị từ chối, vẫn 1 bản mở', function (string $trangThai) {
    $k = khoaCongBo($this);
    $v2 = app(CreateDraftVersion::class)->handle($this->a, $k)['version'];
    $v2->forceFill(['status' => $trangThai])->save();
    cb($this->a, 'POST', route('teacher.courses.versions.store', $k))->assertStatus(422);
    expect(CourseVersion::query()->where('course_id', $k->id)->whereNotNull('open_draft_key')->count())->toBe(1)
        ->and(CourseVersion::query()->where('course_id', $k->id)->count())->toBe(2);
})->with(['NHAP', 'DA_DUYET']);

it('AC-05-07 Bản 2 vừa công bố: tạo bản 3 từ bản 2', function () {
    $k = khoaCongBo($this);
    $v2 = app(CreateDraftVersion::class)->handle($this->a, $k)['version'];
    Lesson::query()->where('course_version_id', $v2->id)->where('title', 'Bài 1')->update(['title' => 'Bài 1 bản 2']);
    KhoaHoc::guiDuyet($v2, $this->a);
    KhoaHoc::duyet($v2, $this->minh);
    KhoaHoc::congBo($v2, $this->lan);
    cb($this->a, 'POST', route('teacher.courses.versions.store', $k))->assertRedirect();
    $v3 = banSo($k, 3);
    expect($v3->status)->toBe(CourseVersionStatus::Nhap)
        ->and(Lesson::query()->where('course_version_id', $v3->id)->where('title', 'Bài 1 bản 2')->exists())->toBeTrue();
});

it('AC-05-09 Sửa bài của bản DA_CONG_BO và DA_THAY_THE: bị từ chối', function () {
    $k = khoaCongBo($this);
    $v2 = app(CreateDraftVersion::class)->handle($this->a, $k)['version'];
    KhoaHoc::guiDuyet($v2, $this->a);
    KhoaHoc::duyet($v2, $this->minh);
    KhoaHoc::congBo($v2, $this->lan);
    foreach ([banSo($k, 2), banSo($k, 1)] as $v) {
        $b3 = Lesson::query()->where('course_version_id', $v->id)->where('title', 'Bài 3')->sole();
        cb($this->a, 'PATCH', route('teacher.lessons.update', $b3), ['title' => 'Sửa'])->assertStatus(422);
        expect($b3->fresh()->title)->toBe('Bài 3');
    }
});

it('AC-05-10 Ngừng bán: NGUNG_BAN, bản công bố không đổi, có nhật ký', function () {
    $k = khoaCongBo($this);
    cb($this->lan, 'POST', route('academic.courses.stop-selling', $k))->assertRedirect();
    expect($k->fresh()->sale_status)->toBe(SaleStatus::NgungBan)->and(banSo($k, 1)->status)->toBe(CourseVersionStatus::DaCongBo)
        ->and(AuditLog::query()->where('action', 'COURSE_SELL_STOP')->count())->toBe(1);
});

it('AC-05-11 Trang khóa đặt "Ngừng bán" trong Thao tác khác (Hủy không gửi yêu cầu nên K vẫn DANG_BAN)', function () {
    $k = khoaCongBo($this);
    $this->signIn($this->lan)->get(route('academic.courses.show', $k))
        ->assertInertia(fn ($p) => $p->component('academic/courses/show')->where('course.sale_status', 'DANG_BAN'));
    expect($k->fresh()->sale_status)->toBe(SaleStatus::DangBan);
});

it('AC-05-16 Bán lại K NGUNG_BAN còn bản công bố: DANG_BAN, có nhật ký', function () {
    $k = khoaCongBo($this);
    app(ChangeSaleStatus::class)->stop($this->lan, $k);
    cb($this->lan, 'POST', route('academic.courses.resume-selling', $k))->assertRedirect();
    expect($k->fresh()->sale_status)->toBe(SaleStatus::DangBan)->and(AuditLog::query()->where('action', 'COURSE_SELL_RESUME')->count())->toBe(1);
});

it('AC-05-17 Ngừng bán K CHUA_BAN: 422', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    cb($this->lan, 'POST', route('academic.courses.stop-selling', $k))->assertStatus(422);
    expect($k->fresh()->sale_status)->toBe(SaleStatus::ChuaBan);
});

it('AC-05-18 Công bố bản 2 khi K NGUNG_BAN: K vẫn NGUNG_BAN', function () {
    $k = khoaCongBo($this);
    app(ChangeSaleStatus::class)->stop($this->lan, $k);
    $v2 = app(CreateDraftVersion::class)->handle($this->a, $k->fresh())['version'];
    KhoaHoc::guiDuyet($v2, $this->a);
    KhoaHoc::duyet($v2, $this->minh);
    cb($this->lan, 'POST', route('academic.course-versions.publish', $v2))->assertRedirect();
    expect($v2->fresh()->status)->toBe(CourseVersionStatus::DaCongBo)->and(banSo($k, 1)->status)->toBe(CourseVersionStatus::DaThayThe)
        ->and($k->fresh()->sale_status)->toBe(SaleStatus::NgungBan);
});

it('AC-05-19/33 A ngừng bán K1, Quang và A bán lại K2: 403', function () {
    $k1 = khoaCongBo($this);
    $k2 = khoaCongBo($this, true, $this->b);
    app(ChangeSaleStatus::class)->stop($this->lan, $k2);
    cb($this->a, 'POST', route('academic.courses.stop-selling', $k1))->assertForbidden();
    cb($this->quang, 'POST', route('academic.courses.resume-selling', $k2))->assertForbidden();
    cb($this->a, 'POST', route('academic.courses.resume-selling', $k2))->assertForbidden();
    expect($k1->fresh()->sale_status)->toBe(SaleStatus::DangBan)->and($k2->fresh()->sale_status)->toBe(SaleStatus::NgungBan);
});

it('AC-05-20 X (người soạn, có vai Giáo vụ) ngừng bán K: thành công', function () {
    $x = taiKhoan('x@edunest.test', RoleCode::GiaoVien, RoleCode::GiaoVu);
    $k = khoaCongBo($this, true, $x);
    cb($x, 'POST', route('academic.courses.stop-selling', $k))->assertRedirect();
    expect($k->fresh()->sale_status)->toBe(SaleStatus::NgungBan);
});

it('AC-05-22 A gọi đổi giá: 403', function () {
    $k = khoaCongBo($this);
    cb($this->a, 'PUT', route('academic.courses.pricing', $k), ['price' => 1000])->assertForbidden();
    expect($k->fresh()->price)->toBe(499000);
});

it('AC-05-23/24 Lan và B gọi tạo bản nháp mới: 403', function () {
    $k = khoaCongBo($this);
    cb($this->lan, 'POST', route('teacher.courses.versions.store', $k))->assertForbidden();
    cb($this->b, 'POST', route('teacher.courses.versions.store', $k))->assertForbidden();
    expect(CourseVersion::query()->where('course_id', $k->id)->count())->toBe(1);
});

it('AC-05-26 K CHUA_BAN có bản công bố: tạo bản 2, công bố, K vẫn CHUA_BAN', function () {
    $k = khoaCongBo($this, false);
    cb($this->a, 'POST', route('teacher.courses.versions.store', $k))->assertRedirect();
    $v2 = banSo($k, 2);
    KhoaHoc::guiDuyet($v2, $this->a);
    KhoaHoc::duyet($v2, $this->minh);
    KhoaHoc::congBo($v2, $this->lan);
    expect($v2->fresh()->status)->toBe(CourseVersionStatus::DaCongBo)->and(banSo($k, 1)->status)->toBe(CourseVersionStatus::DaThayThe)
        ->and($k->fresh()->sale_status)->toBe(SaleStatus::ChuaBan);
});

it('AC-05-27 K NGUNG_BAN: tạo bản 2 được, K vẫn NGUNG_BAN', function () {
    $k = khoaCongBo($this);
    app(ChangeSaleStatus::class)->stop($this->lan, $k);
    cb($this->a, 'POST', route('teacher.courses.versions.store', $k))->assertRedirect();
    expect(banSo($k, 2)->status)->toBe(CourseVersionStatus::Nhap)->and($k->fresh()->sale_status)->toBe(SaleStatus::NgungBan);
});

it('AC-05-28 K chưa công bố lần nào: tạo bản nháp mới bị 422, A vẫn sửa bản 1', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v1 = KhoaHoc::duDieuKien($k, 1);
    cb($this->a, 'POST', route('teacher.courses.versions.store', $k))->assertStatus(422);
    expect(CourseVersion::query()->where('course_id', $k->id)->count())->toBe(1);
    $b = Lesson::query()->where('course_version_id', $v1->id)->first();
    cb($this->a, 'PATCH', route('teacher.lessons.update', $b), ['title' => 'Sửa'])->assertRedirect();
});

it('AC-05-29 K NGUNG_BAN không có bản công bố (dựng thẳng): bán lại bị 422', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $k->forceFill(['sale_status' => 'NGUNG_BAN'])->save();
    cb($this->lan, 'POST', route('academic.courses.resume-selling', $k))->assertStatus(422);
    expect($k->fresh()->sale_status)->toBe(SaleStatus::NgungBan);
});

it('AC-05-35 Ngừng bán K1 CHUA_BAN và bán lại K2 DANG_BAN: 422, không nhật ký', function () {
    $k1 = KhoaHoc::giao($this->lan, $this->a);
    $k2 = khoaCongBo($this, true, $this->b);
    $truoc = AuditLog::query()->count();
    cb($this->lan, 'POST', route('academic.courses.stop-selling', $k1))->assertStatus(422);
    cb($this->lan, 'POST', route('academic.courses.resume-selling', $k2))->assertStatus(422);
    expect($k1->fresh()->sale_status)->toBe(SaleStatus::ChuaBan)->and($k2->fresh()->sale_status)->toBe(SaleStatus::DangBan)
        ->and(AuditLog::query()->count())->toBe($truoc);
});

it('AC-05-36 Câu C7 ngừng dùng sau khi chụp bị bỏ khỏi quiz bản 2 và được báo; bản 1 giữ 10 câu', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v1 = KhoaHoc::duDieuKien($k, 1);
    $b12 = KhoaHoc::bai($v1->sections()->first(), 'Bài 12', LessonType::Quiz);
    $bank = KhoaHoc::nganHang($k, $this->a, 'Chương 1', 10);
    $ids = $bank->questions()->orderBy('id')->pluck('id')->all();
    KhoaHoc::quiz($b12, $ids);
    KhoaHoc::guiDuyet($v1, $this->a);
    KhoaHoc::duyet($v1, $this->minh);
    KhoaHoc::congBo($v1, $this->lan);
    app(QuestionBankActions::class)->deactivate($this->a, Question::query()->findOrFail($ids[6]));

    cb($this->a, 'POST', route('teacher.courses.versions.store', $k))->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'Câu 7?'));
    $v2 = banSo($k, 2);
    $quiz = fn (CourseVersion $v) => QuizQuestion::query()->whereHas('quiz', fn ($q) => $q->where('course_version_id', $v->id))->whereNull('removed_at');
    expect($quiz($v2)->count())->toBe(9)->and($quiz($v2)->where('question_id', $ids[6])->exists())->toBeFalse()
        ->and($quiz($v1)->count())->toBe(10);
});

it('AC-05-32/38 A đã sửa bản đang xét (dù không gửi) bị 403 với lý do "đã sửa"; Minh duyệt được', function (bool $aGui) {
    $aKep = taiKhoan('a.kep@edunest.test', RoleCode::GiaoVien, RoleCode::GiaoVu);
    $k = khoaCongBo($this, true, $aKep);
    $v2 = app(CreateDraftVersion::class)->handle($aKep, $k)['version'];
    app(EditDraftContent::class)->saveLesson($aKep, Lesson::query()->where('course_version_id', $v2->id)->where('title', 'Bài 2')->sole()->id, ['title' => 'Bài 2 A sửa']);
    if ($aGui) {
        KhoaHoc::guiDuyet($v2, $aKep);
        app(ReassignCourseAuthor::class)->handle($this->lan, $k, $this->b->id);
    } else {
        app(ReassignCourseAuthor::class)->handle($this->lan, $k, $this->b->id);
        KhoaHoc::guiDuyet($v2->fresh(), $this->b);
    }

    $r = cb($aKep, 'POST', route('academic.course-versions.approve', $v2))->assertForbidden();
    expect($r->json('message'))->toBe(ReviewerIndependence::DA_SUA);
    cb($aKep, 'POST', route('academic.course-versions.return', $v2), ['reason' => 'Lý do hợp lệ'])->assertForbidden();
    expect($v2->fresh()->status)->toBe(CourseVersionStatus::ChoDuyet)->and(banSo($k, 1)->status)->toBe(CourseVersionStatus::DaCongBo)
        ->and(CourseReview::query()->where('course_version_id', $v2->id)->count())->toBe(0);
    cb($this->minh, 'POST', route('academic.course-versions.approve', $v2))->assertRedirect();
    expect($v2->fresh()->status)->toBe(CourseVersionStatus::DaDuyet);
})->with(['AC-05-32 A gửi' => true, 'AC-05-38 B gửi' => false]);

it('AC-05-37 A từng được giao (chưa sửa) bị 403 lý do "từng được giao"; Minh duyệt được', function () {
    $aKep = taiKhoan('a.kep@edunest.test', RoleCode::GiaoVien, RoleCode::GiaoVu);
    $k = KhoaHoc::giao($this->lan, $aKep);
    app(ReassignCourseAuthor::class)->handle($this->lan, $k, $this->b->id);
    $v = KhoaHoc::duDieuKien($k, 1);
    KhoaHoc::guiDuyet($v, $this->b);

    $r = cb($aKep, 'POST', route('academic.course-versions.approve', $v))->assertForbidden();
    expect($r->json('message'))->toBe(ReviewerIndependence::DA_TUNG_GIAO);
    cb($aKep, 'POST', route('academic.course-versions.return', $v), ['reason' => 'Lý do hợp lệ'])->assertForbidden();
    cb($this->minh, 'POST', route('academic.course-versions.approve', $v))->assertRedirect();
    expect($v->fresh()->status)->toBe(CourseVersionStatus::DaDuyet);
});
