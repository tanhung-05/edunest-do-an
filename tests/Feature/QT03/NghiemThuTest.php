<?php

/*
| QT-03 Biên soạn, duyệt, công bố, bật bán — nhóm P1 của 07a.
*/

use App\Actions\M03\ReassignCourseAuthor;
use App\Actions\M05\EditDraftContent;
use App\Actions\M05\VersionTransitions;
use App\Enums\M01\RoleCode;
use App\Enums\M03\LearningMode;
use App\Enums\M03\SaleStatus;
use App\Enums\M05\CourseVersionStatus;
use App\Enums\M05\LessonType;
use App\Enums\M05\ReviewResult;
use App\Models\M02\Tag;
use App\Models\M03\Course;
use App\Models\M04\Question;
use App\Models\M04\QuizQuestion;
use App\Models\M05\CourseReview;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\M05\LessonResource;
use App\Models\M11\AuditLog;
use App\Models\User;
use App\Support\M05\DraftChecklist;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\KhoaHoc;

pest()->group('P1');

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $this->minh = taiKhoan('minh@edunest.test', RoleCode::GiaoVu);
    $this->a = taiKhoan('hoa.tran@edunest.test', RoleCode::GiaoVien);
    $this->b = taiKhoan('binh.gv@edunest.test', RoleCode::GiaoVien);
    $this->quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
    $this->an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);
});

/** Gọi tuyến dưới vai một tài khoản, dạng JSON để thấy mã 403/422. */
function goi(User $u, string $method, string $url, array $data = [])
{
    return test()->signIn($u)->json($method, $url, $data);
}

function trangThai(CourseVersion $v): CourseVersionStatus
{
    return $v->fresh()->status;
}

it('AC-03-02 Lan tạo khóa với dữ liệu chung, người soạn A, hạn 30/11/2026', function () {
    goi($this->lan, 'POST', route('academic.courses.store'), KhoaHoc::duLieu($this->a))->assertRedirect();

    $k = Course::query()->sole();
    expect($k->sale_status)->toBe(SaleStatus::ChuaBan)
        ->and($k->versions()->count())->toBe(1)
        ->and($k->versions()->first()->status)->toBe(CourseVersionStatus::Nhap)
        ->and($k->versions()->first()->version_no)->toBe(1);
    $this->signIn($this->a)->get(route('teacher.courses.index'))
        ->assertInertia(fn ($p) => $p->component('teacher/courses/index')->where('courses.0.title', 'Hóa 12 nền tảng'));
});

it('AC-03-03 Giá bán 0đ bị từ chối; 1đ được tạo', function () {
    goi($this->lan, 'POST', route('academic.courses.store'), KhoaHoc::duLieu($this->a, ['price' => 0]))->assertStatus(422)->assertJsonValidationErrors('price');
    goi($this->lan, 'POST', route('academic.courses.store'), KhoaHoc::duLieu($this->a, ['price' => 1]))->assertRedirect();
    expect(Course::query()->count())->toBe(1);
});

it('AC-03-04 Giá gốc 498.999đ bị từ chối; 499.000đ được chấp nhận', function () {
    goi($this->lan, 'POST', route('academic.courses.store'), KhoaHoc::duLieu($this->a, ['original_price' => 498999]))->assertStatus(422)->assertJsonValidationErrors('original_price');
    goi($this->lan, 'POST', route('academic.courses.store'), KhoaHoc::duLieu($this->a, ['original_price' => 499000]))->assertRedirect();
    expect(Course::query()->sole()->original_price)->toBe(499000);
});

it('AC-03-05 Kiểu học NGAU_NHIEN, người soạn chỉ có vai Học viên, bỏ trống danh mục: từng việc 422', function () {
    goi($this->lan, 'POST', route('academic.courses.store'), KhoaHoc::duLieu($this->a, ['learning_mode' => 'NGAU_NHIEN']))->assertStatus(422)->assertJsonValidationErrors('learning_mode');
    goi($this->lan, 'POST', route('academic.courses.store'), KhoaHoc::duLieu($this->an))->assertStatus(422)->assertJsonValidationErrors('author_id');
    goi($this->lan, 'POST', route('academic.courses.store'), KhoaHoc::duLieu($this->a, ['category_id' => null]))->assertStatus(422)->assertJsonValidationErrors('category_id');
    expect(Course::query()->count())->toBe(0);
});

it('AC-03-06 Giao lại K cho B: A bị từ chối sửa bài, B sửa được, 3 bài giữ nguyên', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::duDieuKien($k, 3);
    $bai = Lesson::query()->where('course_version_id', $v->id)->orderBy('position')->first();

    goi($this->lan, 'PUT', route('academic.courses.author', $k), ['author_id' => $this->b->id])->assertRedirect();
    goi($this->a, 'PATCH', route('teacher.lessons.update', $bai), ['title' => 'A sửa'])->assertForbidden();
    goi($this->b, 'PATCH', route('teacher.lessons.update', $bai), ['title' => 'B sửa'])->assertRedirect();

    expect($bai->fresh()->title)->toBe('B sửa')
        ->and(Lesson::query()->where('course_version_id', $v->id)->whereNull('removed_at')->count())->toBe(3);
});

it('AC-03-07 A và Lan mở xem trước bản nháp; xem trước không có tuyến ghi tiến độ', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::banMo($k);
    $s = KhoaHoc::chuong($v);
    KhoaHoc::bai($s, 'Bài 1');
    KhoaHoc::bai($s, 'Bài 2', LessonType::BaiDoc, ['content' => '<p>Este là…</p>']);

    foreach ([$this->a, $this->lan] as $u) {
        $this->signIn($u)->get(route('course-versions.preview', $v))->assertOk()
            ->assertInertia(fn ($p) => $p->component('course-preview')->where('sections.0.lessons.1.content', '<p>Este là…</p>'));
    }
    // CR-20: không có tuyến ghi tiến độ trong xem trước; P1 chưa có bảng tiến độ/lượt/XP.
    $this->signIn($this->a)->post('/course-versions/'.$v->id.'/preview/complete')->assertNotFound();
});

it('AC-03-10 A bấm "lên" ở bài 3: thứ tự 1, 3, 2', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::banMo($k);
    $s = KhoaHoc::chuong($v);
    [$b1, $b2, $b3] = [KhoaHoc::bai($s, 'Bài 1'), KhoaHoc::bai($s, 'Bài 2'), KhoaHoc::bai($s, 'Bài 3')];

    goi($this->a, 'POST', route('teacher.content.move', ['kind' => 'lesson', 'id' => $b3->id]), ['direction' => 'up'])->assertRedirect();

    expect(Lesson::query()->where('course_section_id', $s->id)->orderBy('position')->pluck('title')->all())->toBe(['Bài 1', 'Bài 3', 'Bài 2'])
        ->and($b3->fresh()->position)->toBe(2);
});

it('AC-03-11 "Lên" ở bài đầu và "xuống" ở bài cuối không đổi thứ tự, không lỗi', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $s = KhoaHoc::chuong(KhoaHoc::banMo($k));
    [$b1, , $b3] = [KhoaHoc::bai($s, 'Bài 1'), KhoaHoc::bai($s, 'Bài 2'), KhoaHoc::bai($s, 'Bài 3')];

    goi($this->a, 'POST', route('teacher.content.move', ['kind' => 'lesson', 'id' => $b1->id]), ['direction' => 'up'])->assertRedirect()->assertSessionHasNoErrors();
    goi($this->a, 'POST', route('teacher.content.move', ['kind' => 'lesson', 'id' => $b3->id]), ['direction' => 'down'])->assertRedirect()->assertSessionHasNoErrors();
    expect(Lesson::query()->where('course_section_id', $s->id)->orderBy('position')->pluck('title')->all())->toBe(['Bài 1', 'Bài 2', 'Bài 3']);
});

it('AC-03-14 Tải bai4.avi bị từ chối, bài 4 không đổi', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $b4 = KhoaHoc::bai(KhoaHoc::chuong(KhoaHoc::banMo($k)), 'Bài 4', LessonType::Video);
    goi($this->a, 'POST', route('teacher.lessons.video', $b4), ['video' => UploadedFile::fake()->create('bai4.avi', 100, 'video/x-msvideo')])->assertStatus(422);
    expect($b4->fresh()->video_path)->toBeNull();
});

it('AC-03-15 Quiz bài 12 chọn 10 câu, 70%, 10 phút, 3 lượt; câu của khóa khác bị từ chối', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $b12 = KhoaHoc::bai(KhoaHoc::chuong(KhoaHoc::banMo($k)), 'Bài 12', LessonType::Quiz);
    $bank = KhoaHoc::nganHang($k, $this->a, 'Chương 1', 10);
    $l = KhoaHoc::giao($this->lan, $this->a, ['title' => 'Khóa L']);
    $m1 = KhoaHoc::cau(KhoaHoc::nganHang($l, $this->a, 'Chương 1', 0), $this->a, 'M1?');

    goi($this->a, 'PUT', route('teacher.lessons.quiz.update', $b12), ['pass_percent' => 70, 'duration_minutes' => 10, 'max_attempts' => 3])->assertRedirect();
    goi($this->a, 'PUT', route('teacher.lessons.quiz.questions', $b12), ['question_ids' => $bank->questions()->pluck('id')->all()])->assertRedirect();
    goi($this->a, 'PUT', route('teacher.lessons.quiz.questions', $b12), ['question_ids' => [...$bank->questions()->pluck('id')->all(), $m1->id]])->assertStatus(422);

    $quiz = $b12->quiz()->first();
    expect($quiz->pass_percent)->toBe(70)->and($quiz->duration_minutes)->toBe(10)->and($quiz->max_attempts)->toBe(3)
        ->and($quiz->items()->count())->toBe(10);
});

it('AC-03-16 Cờ bài kiểm tra cuối: bài 12 được; bài 8 (cái thứ hai) và bài 9 (không bắt buộc) bị từ chối', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $s = KhoaHoc::chuong(KhoaHoc::banMo($k));
    $b8 = KhoaHoc::bai($s, 'Bài 8', LessonType::Quiz);
    $b9 = KhoaHoc::bai($s, 'Bài 9', LessonType::Quiz, ['is_required' => false]);
    $b12 = KhoaHoc::bai($s, 'Bài 12', LessonType::Quiz);

    goi($this->a, 'PATCH', route('teacher.lessons.update', $b12), ['is_final_test' => true])->assertRedirect();
    goi($this->a, 'PATCH', route('teacher.lessons.update', $b8), ['is_final_test' => true])->assertStatus(422);
    goi($this->a, 'PATCH', route('teacher.lessons.update', $b9), ['is_final_test' => true])->assertStatus(422);
    expect([$b12->fresh()->is_final_test, $b8->fresh()->is_final_test, $b9->fresh()->is_final_test])->toBe([true, false, false]);
});

it('AC-03-17 Điểm đạt 0% và 101% bị từ chối; 1% và 100% được', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $b12 = KhoaHoc::bai(KhoaHoc::chuong(KhoaHoc::banMo($k)), 'Bài 12', LessonType::Quiz);
    foreach ([0 => 422, 1 => 302, 100 => 302, 101 => 422] as $pct => $code) {
        goi($this->a, 'PUT', route('teacher.lessons.quiz.update', $b12), ['pass_percent' => $pct])->assertStatus($code);
    }
    expect($b12->quiz()->first()->pass_percent)->toBe(100);
});

it('AC-03-18 Số lượt 0/11 và thời lượng 0/181 bị từ chối; 1/10 lượt, 1/180 phút được', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $b12 = KhoaHoc::bai(KhoaHoc::chuong(KhoaHoc::banMo($k)), 'Bài 12', LessonType::Quiz);
    foreach ([0 => 422, 1 => 302, 10 => 302, 11 => 422] as $n => $code) {
        goi($this->a, 'PUT', route('teacher.lessons.quiz.update', $b12), ['max_attempts' => $n])->assertStatus($code);
    }
    foreach ([0 => 422, 1 => 302, 180 => 302, 181 => 422] as $n => $code) {
        goi($this->a, 'PUT', route('teacher.lessons.quiz.update', $b12), ['duration_minutes' => $n])->assertStatus($code);
    }
    expect($b12->quiz()->first()->duration_minutes)->toBe(180);
});

it('AC-03-19 E1: bài 2 bắt buộc trống bị nêu (không nêu bài 7 không bắt buộc); điền rồi gửi được', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::banMo($k);
    $s = KhoaHoc::chuong($v);
    KhoaHoc::bai($s, 'Bài 1');
    $b2 = KhoaHoc::bai($s, 'Bài 2', LessonType::BaiDoc, ['content' => null]);
    KhoaHoc::bai($s, 'Bài 7', LessonType::BaiDoc, ['content' => null, 'is_required' => false]);

    $r = goi($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertStatus(422);
    expect(json_encode($r->json('errors'), JSON_UNESCAPED_UNICODE))->toContain('Bài 2')->not->toContain('Bài 7')
        ->and(trangThai($v))->toBe(CourseVersionStatus::Nhap);

    goi($this->a, 'PATCH', route('teacher.lessons.update', $b2), ['content' => '<p>Este</p>'])->assertRedirect();
    goi($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertRedirect();
    expect(trangThai($v))->toBe(CourseVersionStatus::ChoDuyet);
});

it('AC-03-20 E1: video bắt buộc chưa có MP4 và PDF bắt buộc chưa có tệp đều được nêu', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::banMo($k);
    $s = KhoaHoc::chuong($v);
    KhoaHoc::bai($s, 'Bài 1');
    KhoaHoc::bai($s, 'Bài 3', LessonType::Video);
    KhoaHoc::bai($s, 'Bài 6', LessonType::Pdf);

    $errors = json_encode(goi($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertStatus(422)->json('errors'), JSON_UNESCAPED_UNICODE);
    expect($errors)->toContain('Bài 3')->toContain('MP4')->toContain('Bài 6')->toContain('PDF');
});

it('AC-03-21 E2: quiz bắt buộc 0 câu hoặc thiếu điểm đạt bị chặn; đủ thì mục quiz đạt', function (int $soCau, ?int $diem, bool $dat) {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::banMo($k);
    $s = KhoaHoc::chuong($v);
    KhoaHoc::bai($s, 'Bài 1');
    $b12 = KhoaHoc::bai($s, 'Bài 12', LessonType::Quiz);
    $bank = KhoaHoc::nganHang($k, $this->a, 'Chương 1', 1);
    KhoaHoc::quiz($b12, $soCau ? $bank->questions()->pluck('id')->all() : [], $diem);

    $r = goi($this->a, 'POST', route('teacher.course-versions.submit', $v));
    $dat ? $r->assertRedirect() : $r->assertStatus(422)->assertJsonFragment(['checklist.0' => [$r->json('errors')['checklist.0'][0]]]);
    if (! $dat) {
        expect($r->json('errors')['checklist.0'][0])->toContain('Bài 2 "Bài 12"');
    }
})->with([
    '(a) 0 câu, 70%' => [0, 70, false],
    '(b) 1 câu, điểm đạt trống' => [1, null, false],
    '(c) 1 câu, 70%' => [1, 70, true],
]);

it('AC-03-22 Quiz không bắt buộc 0 câu không chặn gửi duyệt', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::duDieuKien($k, 1);
    KhoaHoc::bai($v->sections()->first(), 'Bài 9', LessonType::Quiz, ['is_required' => false]);
    goi($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertRedirect();
    expect(trangThai($v))->toBe(CourseVersionStatus::ChoDuyet);
});

it('AC-03-23 E3: bản 0 bài bị chặn nêu "khóa chưa có bài"', function () {
    $v = KhoaHoc::banMo(KhoaHoc::giao($this->lan, $this->a));
    $r = goi($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertStatus(422);
    expect($r->json('errors')['checklist.0'][0])->toBe('Khóa chưa có bài nào.')->and(trangThai($v))->toBe(CourseVersionStatus::Nhap);
});

it('AC-03-24 E3: không bài bắt buộc bị chặn; đặt bài 1 bắt buộc rồi gửi được', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::banMo($k);
    $s = KhoaHoc::chuong($v);
    $bai = collect(range(1, 12))->map(fn ($i) => KhoaHoc::bai($s, "Bài {$i}", LessonType::BaiDoc, ['is_required' => false]));

    $r = goi($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertStatus(422);
    expect($r->json('errors')['checklist.0'][0])->toBe('Khóa chưa có bài bắt buộc nào.');
    goi($this->a, 'PATCH', route('teacher.lessons.update', $bai[0]), ['is_required' => true])->assertRedirect();
    goi($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertRedirect();
    expect(trangThai($v))->toBe(CourseVersionStatus::ChoDuyet);
});

it('AC-03-25 Danh sách kiểm đúng 3 dòng (bài 2, bài 5 theo E1; bài 12 theo E2), bản vẫn NHAP, chưa chụp', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::banMo($k);
    $s = KhoaHoc::chuong($v);
    KhoaHoc::bai($s, 'Bài 1');
    KhoaHoc::bai($s, 'Bài 2', LessonType::BaiDoc, ['content' => '']);
    KhoaHoc::bai($s, 'Bài 3', LessonType::BaiDoc, ['is_required' => false]);
    KhoaHoc::bai($s, 'Bài 4', LessonType::BaiDoc, ['is_required' => false]);
    KhoaHoc::bai($s, 'Bài 5', LessonType::Pdf);
    foreach (range(6, 11) as $i) {
        KhoaHoc::bai($s, "Bài {$i}", LessonType::BaiDoc, ['is_required' => false]);
    }
    $b12 = KhoaHoc::bai($s, 'Bài 12', LessonType::Quiz);
    KhoaHoc::quiz($b12, []);

    $list = DraftChecklist::evaluate($v);
    expect(array_column($list, 'code'))->toBe(['E1', 'E1', 'E2'])
        ->and(array_column($list, 'lesson_id'))->toBe(Lesson::query()->whereIn('title', ['Bài 2', 'Bài 5', 'Bài 12'])->orderBy('position')->pluck('id')->all());
    $r = goi($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertStatus(422);
    expect(array_keys($r->json('errors')))->toBe(['checklist.0', 'checklist.1', 'checklist.2'])
        ->and(trangThai($v))->toBe(CourseVersionStatus::Nhap)
        ->and(QuizQuestion::query()->whereNotNull('snapshot')->count())->toBe(0);
});

it('AC-03-26 Bản chụp lấy đáp án sửa lúc 09:59:59; sửa lúc 10:00:01 không đổi bản chụp', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::duDieuKien($k, 1);
    $b12 = KhoaHoc::bai($v->sections()->first(), 'Bài 12', LessonType::Quiz);
    $bank = KhoaHoc::nganHang($k, $this->a, 'Chương 1', 0);
    $c3 = KhoaHoc::cau($bank, $this->a, 'Câu 3?', 'B');
    KhoaHoc::quiz($b12, [$c3->id]);
    $dapAn = fn (string $x) => ['options' => array_map(fn ($l) => ['content' => $l, 'is_correct' => $l === $x], ['A', 'B', 'C', 'D'])];

    Carbon::setTestNow('2026-11-21 09:59:59');
    goi($this->a, 'POST', route('teacher.questions.update', $c3), $dapAn('C'))->assertRedirect();
    Carbon::setTestNow('2026-11-21 10:00:00');
    goi($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertRedirect();
    Carbon::setTestNow('2026-11-21 10:00:01');
    goi($this->a, 'POST', route('teacher.questions.update', $c3), $dapAn('D'))->assertRedirect();

    $snap = QuizQuestion::query()->where('question_id', $c3->id)->sole()->snapshot;
    expect(collect($snap['options'])->firstWhere('is_correct', true)['content'])->toBe('C');
});

it('AC-03-27 Minh trả lại có lý do và nhận xét bài 12: bản TRA_LAI, 1 dòng lịch sử, A thấy nhận xét', function () {
    [$k, $v, $b12] = banChoDuyetCoBai12($this);
    $ly = 'Câu 3 không kiểm tra đúng mục tiêu bài 12';
    goi($this->minh, 'POST', route('academic.course-versions.return', $v), ['reason' => $ly, 'comments' => [$b12->id => 'Xem lại câu 3']])->assertRedirect();

    expect(trangThai($v))->toBe(CourseVersionStatus::TraLai)
        ->and(CourseReview::query()->where('course_version_id', $v->id)->sole()->only(['reason']))->toBe(['reason' => $ly]);
    $this->signIn($this->a)->get(route('teacher.courses.show', $k))
        ->assertInertia(fn ($p) => $p->where('reviews.0.comments.0.lesson_id', $b12->id)->where('reviews.0.comments.0.comment', 'Xem lại câu 3'));
});

it('AC-03-28 Trả lại với lý do trống hoặc chỉ khoảng trắng: 422, bản vẫn CHO_DUYET', function () {
    [, $v] = banChoDuyetCoBai12($this);
    goi($this->minh, 'POST', route('academic.course-versions.return', $v), ['reason' => ''])->assertStatus(422)->assertJsonValidationErrors('reason');
    goi($this->minh, 'POST', route('academic.course-versions.return', $v), ['reason' => '   '])->assertStatus(422)->assertJsonValidationErrors('reason');
    expect(trangThai($v))->toBe(CourseVersionStatus::ChoDuyet);
});

it('AC-03-29 Bản TRA_LAI: sửa trước "Bắt đầu sửa" bị từ chối; sau đó sửa được và gửi lại CHO_DUYET', function () {
    [, $v] = banChoDuyetCoBai12($this);
    app(VersionTransitions::class)->returnVersion($this->minh, $v, 'Sai mục tiêu');
    $b2 = Lesson::query()->where('course_version_id', $v->id)->where('title', 'Bài 2')->sole();

    goi($this->a, 'PATCH', route('teacher.lessons.update', $b2), ['title' => 'Bài 2 mới'])->assertStatus(422);
    goi($this->a, 'POST', route('teacher.course-versions.reopen', $v))->assertRedirect();
    expect(trangThai($v))->toBe(CourseVersionStatus::Nhap);
    goi($this->a, 'PATCH', route('teacher.lessons.update', $b2), ['title' => 'Bài 2 mới'])->assertRedirect();
    goi($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertRedirect();
    expect(trangThai($v))->toBe(CourseVersionStatus::ChoDuyet);
});

it('AC-03-30 Lịch sử duyệt giữ "trả lại" rồi "duyệt" theo thứ tự', function () {
    [, $v] = banChoDuyetCoBai12($this);
    app(VersionTransitions::class)->returnVersion($this->minh, $v, 'Sai mục tiêu');
    app(VersionTransitions::class)->reopen($this->a, $v);
    KhoaHoc::guiDuyet($v->fresh(), $this->a);
    goi($this->minh, 'POST', route('academic.course-versions.approve', $v))->assertRedirect();

    expect(CourseReview::query()->where('course_version_id', $v->id)->orderBy('id')->get()->map(fn ($r) => $r->result)->all())
        ->toBe([ReviewResult::TraLai, ReviewResult::Duyet])
        ->and(CourseReview::query()->where('result', 'TRA_LAI')->value('reason'))->toBe('Sai mục tiêu');
});

it('AC-03-31 Rút về nháp: bản NHAP, rời hàng chờ duyệt, có nhật ký', function () {
    [, $v] = banChoDuyetCoBai12($this);
    goi($this->a, 'POST', route('teacher.course-versions.withdraw', $v))->assertRedirect();
    expect(trangThai($v))->toBe(CourseVersionStatus::Nhap)
        ->and(AuditLog::query()->where('action', 'VERSION_WITHDRAW')->where('subject_id', $v->id)->count())->toBe(1);
    $this->signIn($this->lan)->get(route('academic.dashboard'))->assertInertia(fn ($p) => $p->where('reviewQueue', []));
});

it('AC-03-33 Sửa bài bản CHO_DUYET bị từ chối "rút về nháp để sửa"; rút bản DA_DUYET bị từ chối', function () {
    [$k, $v] = banChoDuyetCoBai12($this);
    $b2 = Lesson::query()->where('course_version_id', $v->id)->where('title', 'Bài 2')->sole();
    $r = goi($this->a, 'PATCH', route('teacher.lessons.update', $b2), ['title' => 'x'])->assertStatus(422);
    expect($r->json('message'))->toContain('rút về nháp để sửa');

    KhoaHoc::duyet($v, $this->minh);
    goi($this->a, 'POST', route('teacher.course-versions.withdraw', $v))->assertStatus(422);
    expect(trangThai($v))->toBe(CourseVersionStatus::DaDuyet)->and($b2->fresh()->title)->toBe('Bài 2');
});

it('AC-03-34 X (người soạn có vai Giáo vụ) duyệt bị 403; Y duyệt được', function () {
    $x = taiKhoan('x@edunest.test', RoleCode::GiaoVien, RoleCode::GiaoVu);
    $y = $this->minh;
    $k = KhoaHoc::giao($this->lan, $x);
    $v = KhoaHoc::duDieuKien($k);
    KhoaHoc::guiDuyet($v, $x);

    goi($x, 'POST', route('academic.course-versions.approve', $v))->assertForbidden();
    expect(trangThai($v))->toBe(CourseVersionStatus::ChoDuyet);
    goi($y, 'POST', route('academic.course-versions.approve', $v))->assertRedirect();
    expect(trangThai($v))->toBe(CourseVersionStatus::DaDuyet);
});

it('AC-03-35 X trả lại với lý do hợp lệ bị 403 vì E6, lịch sử không thêm dòng', function () {
    $x = taiKhoan('x@edunest.test', RoleCode::GiaoVien, RoleCode::GiaoVu);
    $k = KhoaHoc::giao($this->lan, $x);
    $v = KhoaHoc::duDieuKien($k);
    KhoaHoc::guiDuyet($v, $x);

    goi($x, 'POST', route('academic.course-versions.return', $v), ['reason' => 'Câu 3 không kiểm tra đúng mục tiêu bài 12'])->assertForbidden();
    expect(trangThai($v))->toBe(CourseVersionStatus::ChoDuyet)->and(CourseReview::query()->count())->toBe(0);
});

it('AC-03-36 X (người soạn) công bố và bật bán K đều được', function () {
    $x = taiKhoan('x@edunest.test', RoleCode::GiaoVien, RoleCode::GiaoVu);
    $k = KhoaHoc::giao($this->lan, $x);
    $v = KhoaHoc::duDieuKien($k);
    KhoaHoc::guiDuyet($v, $x);
    KhoaHoc::duyet($v, $this->minh);

    goi($x, 'POST', route('academic.course-versions.publish', $v))->assertRedirect();
    goi($x, 'POST', route('academic.courses.sell', $k))->assertRedirect();
    expect(trangThai($v))->toBe(CourseVersionStatus::DaCongBo)->and($k->fresh()->sale_status)->toBe(SaleStatus::DangBan);
});

it('AC-03-37 Công bố bản DA_DUYET được (khóa vẫn CHUA_BAN); công bố bản CHO_DUYET bị 422', function () {
    $k1 = KhoaHoc::giao($this->lan, $this->a);
    $v1 = KhoaHoc::duDieuKien($k1);
    KhoaHoc::guiDuyet($v1, $this->a);
    KhoaHoc::duyet($v1, $this->minh);
    $k2 = KhoaHoc::giao($this->lan, $this->a, ['title' => 'Khóa 2']);
    $v2 = KhoaHoc::duDieuKien($k2);
    KhoaHoc::guiDuyet($v2, $this->a);

    goi($this->lan, 'POST', route('academic.course-versions.publish', $v1))->assertRedirect();
    goi($this->lan, 'POST', route('academic.course-versions.publish', $v2))->assertStatus(422);
    expect(trangThai($v1))->toBe(CourseVersionStatus::DaCongBo)->and($k1->fresh()->sale_status)->toBe(SaleStatus::ChuaBan)
        ->and(trangThai($v2))->toBe(CourseVersionStatus::ChoDuyet);
});

it('AC-03-38 Bật bán khóa có bản DA_CONG_BO: DANG_BAN', function () {
    $k = KhoaHoc::daCongBo($this->lan, $this->a, $this->minh);
    goi($this->lan, 'POST', route('academic.courses.sell', $k))->assertRedirect();
    expect($k->fresh()->sale_status)->toBe(SaleStatus::DangBan);
});

it('AC-03-39 Bật bán khi bản 1 còn NHAP bị 422', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    goi($this->lan, 'POST', route('academic.courses.sell', $k))->assertStatus(422);
    expect($k->fresh()->sale_status)->toBe(SaleStatus::ChuaBan);
});

it('AC-03-40 Khóa CHUA_BAN, bản NHAP: URL công khai 404, không có trên trang giáo viên A', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $this->get('/courses/'.$k->slug)->assertNotFound();
    $this->get(route('public.teachers.show', $this->a))->assertInertia(fn ($p) => $p->where('courses', []));
});

it('AC-03-41 Khóa đã công bố nhưng CHUA_BAN: URL công khai 404', function () {
    $k = KhoaHoc::daCongBo($this->lan, $this->a, $this->minh);
    $this->get('/courses/'.$k->slug)->assertNotFound();
});

it('AC-03-42 Hạn 30/11: ngày 30/11 chưa quá hạn; 01/12 có nhãn "Quá hạn" ở A và Giáo vụ, A vẫn sửa được', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $b = KhoaHoc::bai(KhoaHoc::chuong(KhoaHoc::banMo($k)), 'Bài 1');

    Carbon::setTestNow(Carbon::parse('2026-11-30 23:59:59', 'Asia/Ho_Chi_Minh'));
    $this->signIn($this->a)->get(route('teacher.dashboard'))->assertInertia(fn ($p) => $p->where('courses.0.overdue', false));
    Carbon::setTestNow(Carbon::parse('2026-12-01 00:00:00', 'Asia/Ho_Chi_Minh'));
    $this->signIn($this->a)->get(route('teacher.dashboard'))->assertInertia(fn ($p) => $p->where('courses.0.overdue', true));
    $this->signIn($this->lan)->get(route('academic.dashboard'))->assertInertia(fn ($p) => $p->where('overdueCourses.0.overdue', true));
    goi($this->a, 'PATCH', route('teacher.lessons.update', $b), ['title' => 'Bài 1 sửa'])->assertRedirect();
});

it('AC-03-43 Khóa chưa ghi danh: Lan đổi kiểu học TU_DO, thời hạn 6 tháng, gắn thẻ "Luyện thi"', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $tag = Tag::query()->create(['name' => 'Luyện thi', 'slug' => 'luyen-thi']);
    goi($this->lan, 'PATCH', route('academic.courses.update', $k), ['learning_mode' => 'TU_DO', 'access_months' => 6])->assertRedirect();
    goi($this->lan, 'PUT', route('academic.courses.tags', $k), ['tag_ids' => [$tag->id]])->assertRedirect();
    $k->refresh();
    expect($k->learning_mode)->toBe(LearningMode::TuDo)->and($k->access_months)->toBe(6)->and($k->tags->pluck('name')->all())->toBe(['Luyện thi']);
});

it('AC-03-46 Gỡ bài 7 và tài nguyên de8.pdf của bài 8: chỉ là sửa bản nháp, khóa bài khác giữ nguyên', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::banMo($k);
    $s = KhoaHoc::chuong($v);
    $b7 = KhoaHoc::bai($s, 'Bài 7');
    $b8 = KhoaHoc::bai($s, 'Bài 8');
    $r7 = LessonResource::query()->create(['lesson_id' => $b7->id, 'title' => 'de7.pdf', 'file_path' => 'x7', 'original_name' => 'de7.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'position' => 1]);
    $r8 = LessonResource::query()->create(['lesson_id' => $b8->id, 'title' => 'de8.pdf', 'file_path' => 'x8', 'original_name' => 'de8.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'position' => 1]);
    $key8 = $b8->lesson_key;

    goi($this->a, 'DELETE', route('teacher.content.destroy', ['kind' => 'lesson', 'id' => $b7->id]))->assertRedirect();
    goi($this->a, 'DELETE', route('teacher.content.destroy', ['kind' => 'resource', 'id' => $r8->id]))->assertRedirect();

    expect($b7->fresh()->removed_at)->not->toBeNull()->and($r7->fresh()->removed_at)->not->toBeNull()
        ->and($r8->fresh()->removed_at)->not->toBeNull()->and($b8->fresh()->lesson_key)->toBe($key8)
        ->and(Lesson::query()->count())->toBe(2);
    $this->signIn($this->a)->get(route('teacher.courses.show', $k))
        ->assertInertia(fn ($p) => $p->has('sections.0.lessons', 1)->where('sections.0.lessons.0.resources', []));
});

it('AC-03-47 A (Giáo viên) và Quang (Quản trị) gọi tạo khóa: 403', function () {
    goi($this->a, 'POST', route('academic.courses.store'), KhoaHoc::duLieu($this->a))->assertForbidden();
    goi($this->quang, 'POST', route('academic.courses.store'), KhoaHoc::duLieu($this->a))->assertForbidden();
    expect(Course::query()->count())->toBe(0);
});

it('AC-03-48 Lan và An gọi gửi duyệt: 403, bản vẫn NHAP', function () {
    $v = KhoaHoc::duDieuKien(KhoaHoc::giao($this->lan, $this->a));
    goi($this->lan, 'POST', route('teacher.course-versions.submit', $v))->assertForbidden();
    goi($this->an, 'POST', route('teacher.course-versions.submit', $v))->assertForbidden();
    expect(trangThai($v))->toBe(CourseVersionStatus::Nhap);
});

it('AC-03-49 B (không được giao) gửi duyệt K1, sửa bài K1, rút về nháp K2: đều 403', function () {
    $v1 = KhoaHoc::duDieuKien(KhoaHoc::giao($this->lan, $this->a));
    $bai = Lesson::query()->where('course_version_id', $v1->id)->first();
    $v2 = KhoaHoc::duDieuKien(KhoaHoc::giao($this->lan, $this->a, ['title' => 'K2']));
    KhoaHoc::guiDuyet($v2, $this->a);

    goi($this->b, 'POST', route('teacher.course-versions.submit', $v1))->assertForbidden();
    goi($this->b, 'PATCH', route('teacher.lessons.update', $bai), ['title' => 'B'])->assertForbidden();
    goi($this->b, 'POST', route('teacher.course-versions.withdraw', $v2))->assertForbidden();
    expect(trangThai($v1))->toBe(CourseVersionStatus::Nhap)->and($bai->fresh()->title)->toBe('Bài 1')->and(trangThai($v2))->toBe(CourseVersionStatus::ChoDuyet);
});

it('AC-03-50 Lan mở xem bản nháp được; sửa tiêu đề bài 3 bị 403', function () {
    $v = KhoaHoc::duDieuKien(KhoaHoc::giao($this->lan, $this->a));
    $b3 = Lesson::query()->where('course_version_id', $v->id)->where('title', 'Bài 3')->sole();
    $this->signIn($this->lan)->get(route('academic.course-versions.show', $v))->assertOk();
    goi($this->lan, 'PATCH', route('teacher.lessons.update', $b3), ['title' => 'Lan sửa'])->assertForbidden();
    expect($b3->fresh()->title)->toBe('Bài 3');
});

it('AC-03-51/52 A và Quang gọi Duyệt hoặc Trả lại bản CHO_DUYET: 403', function () {
    [, $v] = banChoDuyetCoBai12($this);
    foreach ([$this->a, $this->quang] as $u) {
        goi($u, 'POST', route('academic.course-versions.approve', $v))->assertForbidden();
        goi($u, 'POST', route('academic.course-versions.return', $v), ['reason' => 'Lý do'])->assertForbidden();
    }
    expect(trangThai($v))->toBe(CourseVersionStatus::ChoDuyet);
});

it('AC-03-53/68 A và Quang gọi Công bố bản DA_DUYET hoặc Bật bán: 403', function () {
    [$k1, $v1] = banChoDuyetCoBai12($this);
    KhoaHoc::duyet($v1, $this->minh);
    $k2 = KhoaHoc::daCongBo($this->lan, $this->a, $this->minh, false, ['title' => 'K2']);
    foreach ([$this->a, $this->quang] as $u) {
        goi($u, 'POST', route('academic.course-versions.publish', $v1))->assertForbidden();
        goi($u, 'POST', route('academic.courses.sell', $k2))->assertForbidden();
    }
    expect(trangThai($v1))->toBe(CourseVersionStatus::DaDuyet)->and($k2->fresh()->sale_status)->toBe(SaleStatus::ChuaBan);
});

it('AC-03-54 A (Giáo viên) gọi đổi giá bán: 403, giá không đổi', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    goi($this->a, 'PUT', route('academic.courses.pricing', $k), ['price' => 449000])->assertForbidden();
    expect($k->fresh()->price)->toBe(499000);
});

it('AC-03-55 B và một Học viên gọi xem trước bản nháp: 403', function () {
    $v = KhoaHoc::duDieuKien(KhoaHoc::giao($this->lan, $this->a));
    $this->signIn($this->b)->get(route('course-versions.preview', $v))->assertForbidden();
    $this->signIn($this->an)->get(route('course-versions.preview', $v))->assertForbidden();
});

it('NH-61 staff-preview: người soạn phát được video bản nháp; B và URL lệch phiên bị chặn; bản hết NHAP thì URL cũ bị chặn', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::banMo($k);
    $s = KhoaHoc::chuong($v);
    Storage::disk('local')->put('lessons/video/xem-truoc.mp4', str_repeat('v', 1024));
    KhoaHoc::bai($s, 'Video nháp', LessonType::Video, ['video_path' => 'lessons/video/xem-truoc.mp4', 'video_duration_ms' => 60_000]);

    $url = null;
    $phien = fn () => $this->withCookie(config('session.cookie'), session()->getId());
    $this->signIn($this->a);
    $phien()->get(route('course-versions.preview', $v))->assertOk()
        ->assertInertia(function ($p) use (&$url) {
            $p->component('course-preview')->whereType('sections.0.lessons.0.files.video', 'string');
            $url = $p->toArray()['props']['sections'][0]['lessons'][0]['files']['video'];
        });
    expect($url)->toBeString();
    $phien()->get($url)->assertOk()->assertHeader('Content-Type', 'video/mp4');

    // Người không có quyền xem trước (B) và phiên khác dùng lại URL: 403.
    $this->signIn($this->b);
    $phien()->get($url)->assertForbidden();
    // Phiên khác (URL bị chép sang trình duyệt khác): sid không khớp.
    $this->signIn($this->a)->withCookie(config('session.cookie'), 'phien-khac')->get($url)->assertForbidden();
    // Bản rời NHAP (gửi duyệt) thì người soạn không còn quyền xem trước: URL đã cấp bị chặn lúc phục vụ.
    $this->signIn($this->a);
    $v->forceFill(['status' => CourseVersionStatus::ChoDuyet])->save();
    $phien()->get($url)->assertForbidden();
});

it('AC-03-57 Trang A liệt kê K1 bị trả lại kèm lý do; trang Lan có K2 trong hàng chờ duyệt', function () {
    [$k1, $v1] = banChoDuyetCoBai12($this);
    app(VersionTransitions::class)->returnVersion($this->minh, $v1, 'bài 12 sai mục tiêu');
    $v2 = KhoaHoc::duDieuKien(KhoaHoc::giao($this->lan, $this->b, ['title' => 'K2']));
    KhoaHoc::guiDuyet($v2, $this->b);

    $this->signIn($this->a)->get(route('teacher.dashboard'))
        ->assertInertia(fn ($p) => $p->where('courses.0.id', $k1->id)->where('courses.0.return_reason', 'bài 12 sai mục tiêu'));
    $this->signIn($this->lan)->get(route('academic.dashboard'))
        ->assertInertia(fn ($p) => $p->where('reviewQueue.0.version_id', $v2->id));
});

it('AC-03-58 Mỗi thao tác đúng 1 bản ghi nhật ký có người làm, thời điểm, đối tượng, cũ và mới', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::duDieuKien($k);
    KhoaHoc::guiDuyet($v, $this->a);
    app(VersionTransitions::class)->withdraw($this->a, $v);
    KhoaHoc::guiDuyet($v->fresh(), $this->a);
    app(VersionTransitions::class)->returnVersion($this->minh, $v, 'Sửa bài 1');
    app(VersionTransitions::class)->reopen($this->a, $v);
    KhoaHoc::guiDuyet($v->fresh(), $this->a);
    KhoaHoc::duyet($v, $this->minh);
    KhoaHoc::congBo($v, $this->lan);

    $counts = AuditLog::query()->selectRaw('action, count(*) c')->groupBy('action')->pluck('c', 'action')->map(fn ($c) => (int) $c)->all();
    expect($counts)->toMatchArray(['COURSE_ASSIGN' => 1, 'VERSION_SUBMIT' => 3, 'VERSION_WITHDRAW' => 1, 'VERSION_RETURN' => 1, 'VERSION_APPROVE' => 1, 'VERSION_PUBLISH' => 1]);
    $log = AuditLog::query()->where('action', 'VERSION_APPROVE')->sole();
    expect($log->actor_id)->toBe($this->minh->id)->and($log->subject_id)->toBe($v->id)
        ->and($log->old_values)->toBe(['status' => 'CHO_DUYET'])->and($log->new_values)->toBe(['status' => 'DA_DUYET'])
        ->and($log->created_at)->not->toBeNull();
});

it('AC-03-60/61 A (từng được giao, đã sửa bản) duyệt hoặc trả lại sau khi bị giao lại: 403; Y làm được', function () {
    $aKep = taiKhoan('a.kep@edunest.test', RoleCode::GiaoVien, RoleCode::GiaoVu);
    $k = KhoaHoc::giao($this->lan, $aKep);
    $v = KhoaHoc::duDieuKien($k);
    app(EditDraftContent::class)->saveLesson($aKep, Lesson::query()->where('course_version_id', $v->id)->first()->id, ['title' => 'A sửa']);
    KhoaHoc::guiDuyet($v, $aKep);
    app(ReassignCourseAuthor::class)->handle($this->lan, $k, $this->b->id);

    goi($aKep, 'POST', route('academic.course-versions.approve', $v))->assertForbidden();
    goi($aKep, 'POST', route('academic.course-versions.return', $v), ['reason' => 'Bài 12 sai mục tiêu'])->assertForbidden();
    expect(trangThai($v))->toBe(CourseVersionStatus::ChoDuyet)->and(CourseReview::query()->count())->toBe(0);
    goi($this->minh, 'POST', route('academic.course-versions.return', $v), ['reason' => 'Bài 12 sai mục tiêu'])->assertRedirect();
    expect(trangThai($v))->toBe(CourseVersionStatus::TraLai)->and(CourseReview::query()->count())->toBe(1);
});

it('AC-03-62 Duyệt lúc bản đã rút về NHAP bị 422; gửi lại rồi duyệt: bản chụp mới, đúng 1 dòng "duyệt"', function () {
    [$k, $v] = banChoDuyetCoBai12($this);
    $b2 = Lesson::query()->where('course_version_id', $v->id)->where('title', 'Bài 2')->sole();
    goi($this->a, 'POST', route('teacher.course-versions.withdraw', $v))->assertRedirect();
    goi($this->minh, 'POST', route('academic.course-versions.approve', $v))->assertStatus(422);
    expect(CourseReview::query()->count())->toBe(0);

    goi($this->a, 'PATCH', route('teacher.lessons.update', $b2), ['content' => '<p>Bài 2 mới</p>'])->assertRedirect();
    goi($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertRedirect();
    goi($this->minh, 'POST', route('academic.course-versions.approve', $v))->assertRedirect();
    expect(CourseReview::query()->where('result', 'DUYET')->count())->toBe(1)
        ->and($b2->fresh()->content_fingerprint)->not->toBeNull()
        ->and(QuizQuestion::query()->whereNotNull('snapshot')->count())->toBeGreaterThan(0);
});

it('AC-03-63 Giữa duyệt và công bố: sửa bài bị từ chối, bản công bố giữ đáp án B như lúc duyệt', function () {
    [$k, $v, , $c3] = banChoDuyetCoBai12($this);
    KhoaHoc::duyet($v, $this->minh);
    $b2 = Lesson::query()->where('course_version_id', $v->id)->where('title', 'Bài 2')->sole();
    goi($this->a, 'POST', route('teacher.questions.update', $c3), ['options' => array_map(fn ($l) => ['content' => $l, 'is_correct' => $l === 'C'], ['A', 'B', 'C', 'D'])])->assertRedirect();
    goi($this->a, 'PATCH', route('teacher.lessons.update', $b2), ['title' => 'x'])->assertStatus(422);
    goi($this->lan, 'POST', route('academic.course-versions.publish', $v))->assertRedirect();

    $snap = QuizQuestion::query()->where('question_id', $c3->id)->sole()->snapshot;
    expect(trangThai($v))->toBe(CourseVersionStatus::DaCongBo)
        ->and(collect($snap['options'])->firstWhere('is_correct', true)['content'])->toBe('B');
});

it('AC-03-64 Hai bài cùng cờ kiểm tra cuối (dựng thẳng): gửi duyệt bị chặn nêu bài 8 và bài 12', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::duDieuKien($k, 1);
    $s = $v->sections()->first();
    $bank = KhoaHoc::nganHang($k, $this->a, 'Chương 1', 1);
    $b8 = KhoaHoc::bai($s, 'Bài 8', LessonType::Quiz);
    $b12 = KhoaHoc::bai($s, 'Bài 12', LessonType::Quiz);
    KhoaHoc::quiz($b8, $bank->questions()->pluck('id')->all());
    KhoaHoc::quiz($b12, $bank->questions()->pluck('id')->all());
    $b8->forceFill(['is_final_test' => true])->save();
    DB::table('lessons')->where('id', $b12->id)->update(['is_final_test' => true, 'course_version_id' => $v->id]);
})->skip('UNIQUE final_key ngăn dựng hai cờ cùng bản ngay ở CSDL; ca CR-24 kiểm ở DraftChecklist với dữ liệu trong bộ nhớ.');

it('AC-03-67 A và Quang gọi giao lại K cho B: 403, K vẫn giao A', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    goi($this->a, 'PUT', route('academic.courses.author', $k), ['author_id' => $this->b->id])->assertForbidden();
    goi($this->quang, 'PUT', route('academic.courses.author', $k), ['author_id' => $this->b->id])->assertForbidden();
    expect($k->currentAuthor()->value('user_id'))->toBe($this->a->id);
});

it('AC-03-69 A gọi đổi kiểu học và gắn thẻ: 403', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $tag = Tag::query()->create(['name' => 'Luyện thi', 'slug' => 'luyen-thi']);
    goi($this->a, 'PATCH', route('academic.courses.update', $k), ['learning_mode' => 'TU_DO'])->assertForbidden();
    goi($this->a, 'PUT', route('academic.courses.tags', $k), ['tag_ids' => [$tag->id]])->assertForbidden();
    expect($k->fresh()->learning_mode)->toBe(LearningMode::TuanTu)->and($k->tags()->count())->toBe(0);
});

it('AC-03-70 MP4 500 MB được nhận; 501 MB bị 422 và bài giữ tệp trước', function () {
    Queue::fake();
    $b4 = KhoaHoc::bai(KhoaHoc::chuong(KhoaHoc::banMo(KhoaHoc::giao($this->lan, $this->a))), 'Bài 4', LessonType::Video);
    goi($this->a, 'POST', route('teacher.lessons.video', $b4), ['video' => UploadedFile::fake()->create('bai4a.mp4', 500 * 1024, 'video/mp4')])->assertRedirect();
    $truoc = $b4->fresh()->video_path;
    goi($this->a, 'POST', route('teacher.lessons.video', $b4), ['video' => UploadedFile::fake()->create('bai4b.mp4', 501 * 1024, 'video/mp4')])->assertStatus(422);
    expect($truoc)->not->toBeNull()->and($b4->fresh()->video_path)->toBe($truoc);
});

it('AC-03-71 PDF 50 MB được nhận; 51 MB bị 422', function () {
    $b6 = KhoaHoc::bai(KhoaHoc::chuong(KhoaHoc::banMo(KhoaHoc::giao($this->lan, $this->a))), 'Bài 6', LessonType::Pdf);
    goi($this->a, 'POST', route('teacher.lessons.pdf', $b6), ['pdf' => UploadedFile::fake()->create('de6a.pdf', 50 * 1024, 'application/pdf')])->assertRedirect();
    goi($this->a, 'POST', route('teacher.lessons.pdf', $b6), ['pdf' => UploadedFile::fake()->create('de6b.pdf', 51 * 1024, 'application/pdf')])->assertStatus(422);
});

it('AC-03-72 Ảnh bìa 5 MB được nhận; 6 MB bị 422', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    goi($this->a, 'POST', route('teacher.courses.cover', $k), ['cover' => UploadedFile::fake()->image('bia-a.png')->size(5 * 1024)])->assertRedirect();
    goi($this->a, 'POST', route('teacher.courses.cover', $k), ['cover' => UploadedFile::fake()->image('bia-b.png')->size(6 * 1024)])->assertStatus(422);
    expect($k->fresh()->cover_path)->not->toBeNull();
});

it('AC-03-74 "Lên" ở bài đầu chương 2 không chuyển sang chương 1', function () {
    $v = KhoaHoc::banMo(KhoaHoc::giao($this->lan, $this->a));
    $c1 = KhoaHoc::chuong($v, 'Chương 1');
    $c2 = KhoaHoc::chuong($v, 'Chương 2');
    KhoaHoc::bai($c1, 'Bài 1');
    KhoaHoc::bai($c1, 'Bài 2');
    $b3 = KhoaHoc::bai($c2, 'Bài 3');
    goi($this->a, 'POST', route('teacher.content.move', ['kind' => 'lesson', 'id' => $b3->id]), ['direction' => 'up'])->assertRedirect();
    expect($b3->fresh()->course_section_id)->toBe($c2->id)->and($b3->fresh()->position)->toBe(1);
});

it('AC-03-75 Bỏ cờ bắt buộc khi còn cờ kiểm tra cuối bị từ chối; bỏ cờ cuối trước rồi bỏ bắt buộc được', function () {
    $b12 = KhoaHoc::bai(KhoaHoc::chuong(KhoaHoc::banMo(KhoaHoc::giao($this->lan, $this->a))), 'Bài 12', LessonType::Quiz, ['is_final_test' => true]);
    goi($this->a, 'PATCH', route('teacher.lessons.update', $b12), ['is_required' => false])->assertStatus(422);
    expect($b12->fresh()->only(['is_required', 'is_final_test']))->toBe(['is_required' => true, 'is_final_test' => true]);
    goi($this->a, 'PATCH', route('teacher.lessons.update', $b12), ['is_final_test' => false])->assertRedirect();
    goi($this->a, 'PATCH', route('teacher.lessons.update', $b12), ['is_required' => false])->assertRedirect();
    expect($b12->fresh()->is_required)->toBeFalse();
});

it('AC-03-76 Để trống giá gia hạn: khóa được tạo, "chưa bán gia hạn"', function () {
    goi($this->lan, 'POST', route('academic.courses.store'), KhoaHoc::duLieu($this->a, ['title' => 'Hóa 12 nâng cao', 'price' => 599000, 'extension_price' => null]))->assertRedirect();
    expect(Course::query()->sole()->extension_price)->toBeNull();
});

it('AC-03-77 Khóa thứ hai cùng tên nhận đường dẫn khác; đường dẫn cũ vẫn trỏ khóa thứ nhất', function () {
    $k1 = KhoaHoc::giao($this->lan, $this->a);
    $k2 = KhoaHoc::giao($this->lan, $this->a);
    expect($k1->slug)->toBe('hoa-12-nen-tang')->and($k2->slug)->toBe('hoa-12-nen-tang-2')
        ->and(Course::query()->where('slug', 'hoa-12-nen-tang')->value('id'))->toBe($k1->id);
});

it('AC-03-79/80 Lan, An và B gọi "Bắt đầu sửa" bản TRA_LAI: 403', function () {
    [, $v] = banChoDuyetCoBai12($this);
    app(VersionTransitions::class)->returnVersion($this->minh, $v, 'Sửa');
    foreach ([$this->lan, $this->an, $this->b] as $u) {
        goi($u, 'POST', route('teacher.course-versions.reopen', $v))->assertForbidden();
    }
    expect(trangThai($v))->toBe(CourseVersionStatus::TraLai);
});

it('AC-03-82 Lan và An gọi "Rút về nháp": 403', function () {
    [, $v] = banChoDuyetCoBai12($this);
    goi($this->lan, 'POST', route('teacher.course-versions.withdraw', $v))->assertForbidden();
    goi($this->an, 'POST', route('teacher.course-versions.withdraw', $v))->assertForbidden();
    expect(trangThai($v))->toBe(CourseVersionStatus::ChoDuyet);
});

it('AC-03-83 Công bố bản CHO_DUYET (K1) và bản NHAP (K2): 422, không có nhật ký công bố', function () {
    [, $v1] = banChoDuyetCoBai12($this);
    $v2 = KhoaHoc::banMo(KhoaHoc::giao($this->lan, $this->a, ['title' => 'K2']));
    goi($this->lan, 'POST', route('academic.course-versions.publish', $v1))->assertStatus(422);
    goi($this->lan, 'POST', route('academic.course-versions.publish', $v2))->assertStatus(422);
    expect(trangThai($v1))->toBe(CourseVersionStatus::ChoDuyet)->and(trangThai($v2))->toBe(CourseVersionStatus::Nhap)
        ->and(AuditLog::query()->where('action', 'VERSION_PUBLISH')->count())->toBe(0);
});

it('AC-03-84 A và Quang gửi "Giao soạn" khóa "Sinh 12": 403, không có khóa, không có nhật ký', function () {
    foreach ([$this->a, $this->quang] as $u) {
        goi($u, 'POST', route('academic.courses.store'), KhoaHoc::duLieu($this->b, ['title' => 'Sinh 12']))->assertForbidden();
    }
    expect(Course::query()->count())->toBe(0)->and(AuditLog::query()->where('action', 'COURSE_ASSIGN')->count())->toBe(0);
    $this->signIn($this->b)->get(route('teacher.courses.index'))->assertInertia(fn ($p) => $p->where('courses', []));
});

it('AC-03-85 E2 (CR-188): quiz bắt buộc thiếu thời lượng bị chặn; đặt 10 phút, 3 lượt rồi gửi được', function () {
    $k = KhoaHoc::giao($this->lan, $this->a);
    $v = KhoaHoc::duDieuKien($k, 1);
    $b12 = KhoaHoc::bai($v->sections()->first(), 'Bài 12', LessonType::Quiz);
    KhoaHoc::quiz($b12, KhoaHoc::nganHang($k, $this->a, 'Chương 1', 10)->questions()->pluck('id')->all(), 70, null, null);

    $r = goi($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertStatus(422);
    expect($r->json('errors')['checklist.0'][0])->toContain('Bài 12')->toContain('thời lượng');
    goi($this->a, 'PUT', route('teacher.lessons.quiz.update', $b12), ['pass_percent' => 70, 'duration_minutes' => 10, 'max_attempts' => 3])->assertRedirect();
    goi($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertRedirect();
    expect(trangThai($v))->toBe(CourseVersionStatus::ChoDuyet);
});

/**
 * Bản 1 `CHO_DUYET` có bài 1, bài 2 (bài viết bắt buộc) và bài 12 (quiz bắt buộc có câu 3 đáp án B).
 *
 * @return array{0: Course, 1: CourseVersion, 2: Lesson, 3: Question}
 */
function banChoDuyetCoBai12($t): array
{
    $k = KhoaHoc::giao($t->lan, $t->a);
    $v = KhoaHoc::duDieuKien($k, 2);
    $b12 = KhoaHoc::bai($v->sections()->first(), 'Bài 12', LessonType::Quiz);
    $c3 = KhoaHoc::cau(KhoaHoc::nganHang($k, $t->a, 'Chương 1', 0), $t->a, 'Câu 3?', 'B');
    KhoaHoc::quiz($b12, [$c3->id]);
    KhoaHoc::guiDuyet($v, $t->a);

    return [$k, $v->fresh(), $b12, $c3];
}
