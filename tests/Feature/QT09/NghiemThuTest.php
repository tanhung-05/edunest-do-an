<?php

use App\Actions\M09\MarkLessonCompleted;
use App\Actions\M09\RecordWatchBeat;
use App\Actions\M10\FinalizeAttempt;
use App\Enums\M03\LearningMode;
use App\Enums\M03\SaleStatus;
use App\Enums\M05\CourseVersionStatus;
use App\Enums\M08\LearningStatus;
use App\Enums\M10\AttemptCloseReason;
use App\Models\M05\CourseVersion;
use App\Models\M09\LessonProgress;
use App\Models\M10\Certificate;
use App\Queries\M09\LearningEntry;
use App\Queries\M12\EnrollmentOverview;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;
use Tests\Support\KhoaHoc;

pest()->group('P4');

beforeEach(function () {
    $this->travelTo(HocTap::at('09:00:00'));
    Storage::fake('local');
    $this->h = new HocTap;
});

function mocXem(HocTap $h, string $time, int $position): array
{
    test()->travelTo(HocTap::at($time));

    return app(RecordWatchBeat::class)->handle($h->a, $h->enrollment, $h->video, $position);
}

function videoP4(HocTap $h): string
{
    $h->signIn(test());

    return test()->get($h->lessonUrl())->assertOk()->viewData('page')['props']['video_url'];
}

function banKhac(HocTap $h): CourseVersion
{
    $h->version->update(['status' => CourseVersionStatus::DaThayThe, 'superseded_at' => now()]);
    $version = $h->course->versions()->create([
        'version_no' => 2, 'status' => CourseVersionStatus::DaCongBo, 'published_at' => now(),
    ]);
    $h->course->update(['current_version_id' => $version->id]);

    return $version;
}

it('AC-09-01 Xem liên tục đến 90% thì hoàn thành đúng một lần', function () {
    $h = $this->h;
    $h->signIn($this);
    $this->get($h->lessonUrl())->assertOk();
    $url = $h->lessonUrl('watch-beats.store');
    for ($second = 0; $second <= 540; $second += 10) {
        $this->travelTo(HocTap::at('10:00:00')->addSeconds($second));
        $this->postJson($url, ['position_ms' => $second * 1000])->assertOk()
            ->assertJsonPath('completed', $second === 540);
    }
    expect($h->enrollment->lessonProgress()->sole()->valid_watch_ms)->toBe(540000);
});

it('AC-09-02 539 giây chưa đủ, 540 giây thì đạt', function () {
    mocXem($this->h, '10:00:00', 0);
    expect(mocXem($this->h, '10:09:00', 539000)['progress']->completed_at)->toBeNull();
    expect(mocXem($this->h, '10:09:01', 540000)['progress']->completed_at)->not->toBeNull();
});

it('AC-09-03 541 giây giữ mốc hoàn thành ở giây 540', function () {
    mocXem($this->h, '10:00:00', 0);
    $at = mocXem($this->h, '10:09:00', 540000)['progress']->completed_at;
    $later = mocXem($this->h, '10:09:01', 541000)['progress']->completed_at;
    expect($at)->not->toBeNull()->and($later->equalTo($at))->toBeTrue();
});

it('AC-09-04 Phiên mới mở lại vị trí dừng đã lưu', function () {
    $h = $this->h;
    mocXem($h, '10:00:00', 10000);
    $h->signIn($this, cookie: str_repeat('z', 64));
    $this->get($h->lessonUrl())->assertOk()->assertInertia(fn ($page) => $page->where('position_ms', 10000));
});

it('AC-09-05 Chọn bài mở đầu tiên nếu chưa học dở', function () {
    $h = $this->h;
    $h->mark($h->read);
    expect(LearningEntry::for($h->a, $h->enrollment)['lesson_id'])->toBe($h->video->id);
});

it('AC-09-06 Mở bài đọc không hoàn thành và bấm lặp giữ giờ cũ', function () {
    $h = $this->h;
    $h->signIn($this);
    $this->get($h->lessonUrl(lesson: $h->read))->assertOk();
    expect($h->enrollment->lessonProgress()->sole()->completed_at)->toBeNull();
    $url = $h->lessonUrl('complete', $h->read);
    $this->post($url)->assertRedirect();
    $at = $h->enrollment->lessonProgress()->sole()->completed_at;
    $this->travelTo(HocTap::at('10:01:00'));
    $this->post($url)->assertRedirect();
    expect($h->enrollment->lessonProgress()->sole()->completed_at->equalTo($at))->toBeTrue();
});

it('AC-09-07 Hiện đường mở quiz đúng khóa', function () {
    $h = $this->h;
    $h->signIn($this)->get($h->lessonUrl(lesson: $h->quizLesson))->assertOk()
        ->assertInertia(fn ($page) => $page->where('quiz_url', $h->quizUrl()));
});

it('AC-09-08 Học tự do mở bài cuối sớm nhưng còn bài bắt buộc', function () {
    $h = $this->h;
    $h->signIn($this)->get($h->lessonUrl(lesson: $h->quizLesson))->assertOk();
    $h->mark($h->read);
    expect($h->submit(3)->passed)->toBeTrue()->and($h->enrollment->refresh()->learning_status)->toBe(LearningStatus::DangHoc);
});

it('AC-09-09 Tải tài nguyên riêng của bài', function () {
    $h = $this->h;
    $h->signIn($this);
    $props = $this->get($h->lessonUrl())->assertOk()->viewData('page')['props'];
    $this->get($props['resources'][0]['url'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('AC-09-10 Tiến độ chỉ đếm bài bắt buộc', function () {
    $h = $this->h;
    $h->mark($h->optional);
    $h->mark($h->read);
    expect(EnrollmentOverview::progress($h->enrollment))->toBe(['completed' => 1, 'required' => 3, 'percent' => '33.3']);
});

it('AC-09-12 Chỉ trả bài thuộc phiên bản đang gắn', function () {
    $h = $this->h;
    $v2 = banKhac($h);
    $other = KhoaHoc::bai(KhoaHoc::chuong($v2), 'Bài của bản 2');
    $h->signIn($this)->getJson($h->lessonUrl(lesson: $other))->assertForbidden();
});

it('AC-09-13 Khách phải đăng nhập trước khi vào bài riêng', function () {
    $this->get($this->h->lessonUrl())->assertRedirect(route('login'));
});

it('AC-09-14 URL cũ hết hạn và tải lại cấp URL khác', function () {
    $h = $this->h;
    $old = videoP4($h);
    $this->travel(16)->minutes();
    $this->getJson($old)->assertForbidden();
    $new = videoP4($h);
    expect($new)->not->toBe($old);
    $this->get($new)->assertOk();
});

it('AC-09-15 Sửa chữ ký khiến yêu cầu bị từ chối', function () {
    $url = videoP4($this->h);
    $this->getJson($url.'0')->assertForbidden();
});

it('AC-09-16 Chặn bài tại đúng giây hết hạn', function () {
    $h = $this->h;
    $h->enrollment->update(['het_han_luc' => HocTap::at('10:00:00')]);
    $h->signIn($this);
    $this->travelTo(HocTap::at('09:59:59'));
    $this->get($h->lessonUrl())->assertOk();
    $this->travelTo(HocTap::at('10:00:00'));
    $this->getJson($h->lessonUrl())->assertForbidden();
});

it('AC-09-17 Hết hạn vẫn đọc kết quả và lịch sử đơn', function () {
    $h = $this->h;
    $attempt = $h->submit();
    $url = videoP4($h);
    $h->enrollment->update(['het_han_luc' => now()]);
    $this->getJson($h->lessonUrl())->assertForbidden();
    $this->getJson($url)->assertForbidden();
    $this->get(route('student.attempts.result', $attempt))->assertOk();
    $this->get(route('student.orders.index'))->assertOk();
});

it('AC-09-18 Thu hồi khóa bài nhưng vẫn đọc lịch sử đơn', function () {
    $h = $this->h;
    $h->enrollment->update(['revoked_at' => now()]);
    $h->signIn($this)->getJson($h->lessonUrl())->assertForbidden();
    $this->get(route('student.orders.index'))->assertOk();
});

it('AC-09-19 Bài bắt buộc đứng trước khóa bài tiếp theo', function () {
    $h = $this->h;
    $h->course->update(['learning_mode' => LearningMode::TuanTu]);
    $h->signIn($this)->getJson($h->lessonUrl())->assertForbidden()->assertJsonPath('message', 'Hoàn thành bài bắt buộc trước: Bài đọc.');
    $h->mark($h->read);
    $this->get($h->lessonUrl())->assertOk();
});

it('AC-09-20 Bài xem thử bắt buộc không khóa bài kế', function () {
    $h = $this->h;
    $h->course->update(['learning_mode' => LearningMode::TuanTu]);
    $h->read->update(['is_preview' => true]);
    $h->signIn($this)->get($h->lessonUrl())->assertOk();
});

it('AC-09-21 Tua quá mức cập nhật vị trí mà không cộng đoạn', function () {
    mocXem($this->h, '10:00:00', 100000);
    $result = mocXem($this->h, '10:00:10', 126000);
    expect($result['counted'])->toBeFalse()->and($result['progress']->last_position_ms)->toBe(126000)
        ->and($result['progress']->valid_watch_ms)->toBe(0);
});

it('AC-09-22 Dung sai 25 giây được nhận và 26 giây bị bỏ', function () {
    mocXem($this->h, '10:00:00', 100000);
    expect(mocXem($this->h, '10:00:10', 125000)['counted'])->toBeTrue();
    expect(mocXem($this->h, '10:00:20', 151000)['counted'])->toBeFalse();
});

it('AC-09-23 Không tự khai hoàn thành video', function () {
    $h = $this->h;
    $h->signIn($this)->postJson($h->lessonUrl('complete'))->assertUnprocessable();
    expect($h->enrollment->lessonProgress()->exists())->toBeFalse();
});

it('AC-09-24 Giáo vụ không ghi tiến độ của học viên', function () {
    $h = $this->h;
    $this->signIn($h->g)->postJson($h->lessonUrl('complete', $h->read))->assertForbidden();
    $this->postJson($h->lessonUrl('watch-beats.store'), ['position_ms' => 0])->assertForbidden();
});

it('AC-09-25 Học viên khác không ghi tiến độ thay chủ', function () {
    $h = $this->h;
    $h->signIn($this, HocTap::student('khac'))->postJson($h->lessonUrl('complete', $h->read))->assertForbidden();
    $this->postJson($h->lessonUrl('watch-beats.store'), ['position_ms' => 0])->assertForbidden();
});

it('AC-09-26 Chưa đủ bài bắt buộc thì vẫn đang học', function () {
    $h = $this->h;
    $h->mark($h->video);
    app(MarkLessonCompleted::class)->handle($h->a, $h->enrollment, $h->read);
    expect($h->enrollment->refresh()->learning_status)->toBe(LearningStatus::DangHoc);
});

it('AC-09-27 Học lại giữ nguyên chứng nhận và giờ hoàn thành', function () {
    $h = $this->h;
    $h->mark($h->video);
    $h->submit(3);
    $action = app(MarkLessonCompleted::class);
    $action->handle($h->a, $h->enrollment, $h->read);
    $at = $h->enrollment->refresh()->completed_at;
    $this->travel(1)->hours();
    $action->handle($h->a, $h->enrollment, $h->read);
    expect($h->enrollment->refresh()->completed_at->equalTo($at))->toBeTrue()->and(Certificate::query()->count())->toBe(1);
});

it('AC-09-28 Không cấp URL video cho tài khoản ngoài phạm vi', function () {
    $h = $this->h;
    $h->signIn($this, HocTap::student('khong-ghi-danh'))->getJson($h->lessonUrl())->assertForbidden();
});

it('AC-09-29 Bản mới công bố không tự chuyển ghi danh cũ', function () {
    $h = $this->h;
    $new = KhoaHoc::bai(KhoaHoc::chuong(banKhac($h)), 'Bài 13');
    $h->signIn($this)->getJson($h->lessonUrl(lesson: $new))->assertForbidden();
    $this->get($h->lessonUrl())->assertOk();
});

it('AC-09-30 Bản cũ báo có bản mới, học viên mới thấy cây bản mới', function () {
    $h = $this->h;
    $v2 = banKhac($h);
    $new = KhoaHoc::bai(KhoaHoc::chuong($v2), 'Bài bản hai');
    $h->signIn($this)->get($h->lessonUrl())->assertInertia(fn ($page) => $page->where('has_new_version', true)->has('lessons', 4));
    $student = HocTap::student('ban-hai');
    $h->enroll($student, $v2);
    $this->flushSession();
    session()->migrate(true);
    $h->signIn($this, $student)->get($h->lessonUrl(lesson: $new))->assertInertia(fn ($page) => $page
        ->where('has_new_version', false)->has('lessons', 1)->where('progress.required', 1));
});

it('AC-09-31 Range video trả 206 và đúng chiều dài', function () {
    $url = videoP4($this->h);
    $this->get($url, ['Range' => 'bytes=10-19'])->assertStatus(206)->assertHeader('Content-Range', 'bytes 10-19/2048');
});

it('AC-09-32 URL ký của phiên cũ bị từ chối dù cùng cookie', function () {
    $url = videoP4($this->h);
    session()->migrate(true);
    $this->withCookie(config('session.cookie'), session()->getId())->getJson($url)->assertForbidden();
});

it('AC-09-33 URL ký bị từ chối khi đổi cookie thiết bị', function () {
    $url = videoP4($this->h);
    $this->withCookie('edunest_device', str_repeat('z', 64))->getJson($url)->assertForbidden();
});

it('AC-09-34 Hiệu lực ghi danh được xét lại khi đọc URL còn hạn', function () {
    $url = videoP4($this->h);
    $this->h->enrollment->update(['het_han_luc' => now()]);
    $this->getJson($url)->assertForbidden();
});

it('AC-09-35 Hết hạn quyền học không cấp URL thay thế', function () {
    $url = videoP4($this->h);
    $this->travel(16)->minutes();
    $this->h->enrollment->update(['het_han_luc' => now()]);
    $this->getJson($url)->assertForbidden();
    $this->getJson($this->h->lessonUrl())->assertForbidden();
});

it('AC-09-36 So 90% bằng mili giây, không làm tròn trước', function () {
    mocXem($this->h, '10:00:00', 0);
    expect(mocXem($this->h, '10:09:00', 539950)['progress']->completed_at)->toBeNull();
    expect(mocXem($this->h, '10:09:00.050', 540000)['progress']->completed_at)->not->toBeNull();
});

it('AC-09-37 Hợp các đoạn chồng nhau không đếm hai lần', function () {
    mocXem($this->h, '10:00:00', 0);
    mocXem($this->h, '10:00:10', 10000);
    mocXem($this->h, '10:00:11', 5000);
    $result = mocXem($this->h, '10:00:21', 15000);
    expect($result['progress']->watched_segments)->toBe([[0, 15000]])->and($result['progress']->valid_watch_ms)->toBe(15000);
});

it('AC-09-38 Mốc 2x hợp lệ theo giờ máy chủ', function () {
    mocXem($this->h, '10:00:00', 1000);
    $result = mocXem($this->h, '10:00:10', 21000);
    expect($result['counted'])->toBeTrue()->and($result['progress']->valid_watch_ms)->toBe(20000);
});

it('AC-09-39 Bài bắt buộc cuối cấp chứng nhận cùng thời điểm', function () {
    $h = $this->h;
    $h->mark($h->read);
    $h->submit(3);
    mocXem($h, '10:00:00', 0);
    mocXem($h, '10:09:00', 540000);
    expect($h->enrollment->refresh()->learning_status)->toBe(LearningStatus::HoanThanh)
        ->and($h->enrollment->certificate()->sole()->enrollment_id)->toBe($h->enrollment->id);
});

it('AC-09-40 URL tài nguyên hết hạn được thay khi còn quyền', function () {
    $h = $this->h;
    $h->signIn($this);
    $getUrl = fn () => $this->get($h->lessonUrl())->assertOk()->viewData('page')['props']['resources'][0]['url'];
    $old = $getUrl();
    $this->travel(16)->minutes();
    $this->getJson($old)->assertForbidden();
    $this->get($getUrl())->assertOk();
});

it('AC-09-41 Đường dẫn riêng và URL thiếu chữ ký không trả tệp', function () {
    $h = $this->h;
    $h->signIn($this);
    expect($this->get('/storage/lessons/resources/p4.pdf')->getStatusCode())->toBeIn([403, 404]);
    $this->getJson(route('student.lessons.files.stream', ['course' => $h->course->id, 'lesson' => $h->video->id, 'file' => $h->resource->id]))->assertForbidden();
});

it('AC-09-42 Ghi danh dựng thu hồi chốt lượt nhưng không hoàn thành bài', function () {
    $h = $this->h;
    $attempt = $h->start();
    $h->fill($attempt, 3);
    $h->enrollment->update(['revoked_at' => now()]);
    $result = app(FinalizeAttempt::class)->handle($attempt);
    expect($result->close_reason)->toBe(AttemptCloseReason::ThuHoi)->and($result->passed)->toBeTrue()
        ->and(LessonProgress::query()->where('lesson_id', $h->quizLesson->id)->exists())->toBeFalse();
});

it('AC-09-43 Video còn chữ ký bị từ chối sau khi ghi danh bị thu hồi', function () {
    $url = videoP4($this->h);
    $this->h->enrollment->update(['revoked_at' => now()]);
    $this->getJson($url)->assertForbidden();
});

it('AC-09-44 Tài nguyên còn chữ ký bị từ chối sau khi ghi danh bị thu hồi', function () {
    $h = $this->h;
    $h->signIn($this);
    $resource = $this->get($h->lessonUrl())->assertOk()->viewData('page')['props']['resources'][0]['url'];
    $h->enrollment->update(['revoked_at' => now()]);
    $this->getJson($resource)->assertForbidden();
});

it('AC-09-51 Nhãn sắp hết hạn bắt đầu ở đúng bảy ngày', function () {
    $h = $this->h;
    $at = HocTap::at('10:00:00');
    $h->enrollment->update(['het_han_luc' => $at->addDays(7)]);
    $h->signIn($this);
    $this->travelTo($at->subSecond());
    $this->get(route('student.dashboard'))->assertInertia(fn ($page) => $page->where('learningCourses.0.expires_soon', false));
    $this->travelTo($at);
    $this->get(route('student.dashboard'))->assertInertia(fn ($page) => $page->where('learningCourses.0.expires_soon', true));
});

it('AC-05-03 Bản mới công bố không lấy cây và tiến độ của ghi danh cũ', function () {
    $h = $this->h;
    $h->mark($h->read);
    $new = KhoaHoc::bai(KhoaHoc::chuong(banKhac($h)), 'Bài mới');
    $h->signIn($this)->get($h->lessonUrl())->assertInertia(fn ($page) => $page
        ->where('has_new_version', true)->has('lessons', 4)->where('progress.completed', 1)->where('progress.required', 3));
    $student = HocTap::student('ban-moi');
    $h->enroll($student, $new->version()->firstOrFail());
    $this->flushSession();
    session()->migrate(true);
    $h->signIn($this, $student)->get($h->lessonUrl(lesson: $new))->assertInertia(fn ($page) => $page->where('has_new_version', false));
});

it('AC-05-13 Ngừng bán nhưng ghi danh còn hạn mở bài tuần tự đã mở', function () {
    $h = $this->h;
    $h->course->update(['learning_mode' => LearningMode::TuanTu, 'sale_status' => SaleStatus::NgungBan]);
    $h->mark($h->read);
    $h->enrollment->update(['het_han_luc' => now()->addYear()]);
    $h->signIn($this)->get($h->lessonUrl())->assertOk();
});

it('AC-06-32 Đường Vào học chọn bài của phiên bản ghi danh dù khóa ngừng bán', function () {
    $h = $this->h;
    $h->course->update(['sale_status' => SaleStatus::NgungBan]);
    banKhac($h);
    $h->mark($h->read);
    $h->signIn($this)->get(route('student.courses.index', ['course' => $h->course->id]))->assertRedirect($h->lessonUrl());
    $this->get($h->lessonUrl())->assertInertia(fn ($page) => $page->where('has_new_version', true));
});
