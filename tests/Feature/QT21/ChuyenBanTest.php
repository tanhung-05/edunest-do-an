<?php

use App\Actions\M03\SetCoursePrerequisite;
use App\Actions\M05\CreateDraftVersion;
use App\Actions\M05\SwitchEnrollmentVersion;
use App\Models\M05\CourseSection;
use App\Models\M05\EnrollmentVersionSwitch;
use App\Models\M05\Lesson;
use App\Models\M09\LessonProgress;
use App\Models\User;
use App\Queries\M05\VersionDiff;
use App\Support\DomainRejection;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;
use Tests\Support\KhoaHoc;

pest()->group('P6');

beforeEach(function () {
    Storage::fake('local');
    $this->h = new HocTap;
    $this->reviewer = User::query()->where('email', 'duyet-p4@edunest.test')->sole();
});

/** Bản 2: giữ mọi bài, thêm "Bài mới", sửa nội dung "Bài đọc". */
function banHai(object $t)
{
    $v2 = app(CreateDraftVersion::class)->handle($t->h->t, $t->h->course->fresh())['version'];
    $section = CourseSection::query()->where('course_version_id', $v2->id)->firstOrFail();
    KhoaHoc::bai($section, 'Bài mới');
    Lesson::query()->where('course_version_id', $v2->id)->where('title', 'Bài đọc')->firstOrFail()->forceFill(['content' => '<p>Nội dung đã sửa</p>'])->save();

    return $v2;
}

it('CR-196 So sánh bản chờ duyệt với bản đang công bố: thêm, đổi nội dung', function () {
    $v2 = banHai($this);
    KhoaHoc::guiDuyet($v2, $this->h->t);
    $diff = VersionDiff::against($v2->fresh());
    expect($diff['base'])->toBe(1)->and($diff['added'])->toBe(['Bài mới'])->and($diff['removed'])->toBe([]);
    $this->signIn($this->h->g)->get(route('academic.course-versions.show', $v2))->assertInertia(fn ($p) => $p->where('diff.added.0', 'Bài mới'));
});

it('CB-1 DT-48 CR-150 Chuyển sang bản mới giữ tiến độ bài không đổi nội dung, quiz làm lại; chuyển lặp bị từ chối', function () {
    $this->h->mark($this->h->read);
    $this->h->mark($this->h->video);
    $v2 = banHai($this);
    KhoaHoc::guiDuyet($v2, $this->h->t);
    KhoaHoc::duyet($v2, $this->reviewer);
    KhoaHoc::congBo($v2, $this->h->g);

    $this->h->signIn($this)->get($this->h->lessonUrl())->assertInertia(fn ($p) => $p->whereNot('switch_version_url', null));
    $this->h->signIn($this)->post(route('student.enrollments.switch-version', $this->h->enrollment))->assertRedirect(route('student.courses.index'));
    $e = $this->h->enrollment->fresh();
    $kept = Lesson::query()->whereIn('id', LessonProgress::query()->where('enrollment_id', $e->id)->select('lesson_id'))
        ->where('course_version_id', $v2->id)->pluck('title')->all();
    expect($e->course_version_id)->toBe($v2->id)->and($kept)->toBe(['Video 10 phút'])
        ->and(EnrollmentVersionSwitch::query()->sole()->kept_lessons)->toBe(1);
    expect(fn () => app(SwitchEnrollmentVersion::class)->handle($this->h->a, $e))->toThrow(DomainRejection::class);
    expect(EnrollmentVersionSwitch::query()->count())->toBe(1);
});

it('CR-67 Khóa tiên quyết: chặn ghi danh chưa bắt đầu; ghi danh đã học không bị ảnh hưởng; không tạo vòng', function () {
    $pre = KhoaHoc::giao($this->h->g, $this->h->t, ['title' => 'Nhập môn']);
    $this->h->signIn($this)->get($this->h->lessonUrl())->assertOk();
    $started = $this->h->enrollment->fresh();
    expect($started->started_at)->not->toBeNull();
    $c = HocTap::student('chi');
    $this->h->enroll($c);
    $this->signIn($this->h->g)->put(route('academic.courses.prerequisite', $this->h->course), ['prerequisite_course_id' => $pre->id])->assertRedirect();
    expect($this->h->course->fresh()->prerequisite_course_id)->toBe($pre->id);
    $this->h->signIn($this, $c)->get($this->h->lessonUrl())->assertForbidden();
    $this->h->signIn($this)->get($this->h->lessonUrl())->assertOk();
    expect(fn () => app(SetCoursePrerequisite::class)->handle($this->h->g, $pre, $this->h->course->id))->toThrow(DomainRejection::class)
        ->and(fn () => app(SetCoursePrerequisite::class)->handle($this->h->g, $this->h->course, $this->h->course->id))->toThrow(DomainRejection::class);
    $this->signIn($this->h->t)->put(route('academic.courses.prerequisite', $this->h->course), ['prerequisite_course_id' => null])->assertForbidden();
});
