<?php

/*
| QT-03 TV-07 đọc thời lượng video (AC-03-13, AC-03-73, AC-03-81). ffprobe được thay bằng bản giả.
*/

use App\Enums\M01\RoleCode;
use App\Enums\M05\CourseVersionStatus;
use App\Enums\M05\LessonType;
use App\Jobs\M05\ReadVideoDuration;
use App\Models\M04\QuizQuestion;
use App\Models\M05\Lesson;
use App\Support\M05\DraftChecklist;
use App\Support\M05\VideoProbe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\KhoaHoc;

pest()->group('P1');

beforeEach(function () {
    Storage::fake('local');
    $this->lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $this->a = taiKhoan('hoa.tran@edunest.test', RoleCode::GiaoVien);
    $this->probe = new class extends VideoProbe
    {
        public ?int $ms = null;

        public function durationMs(string $absolutePath): ?int
        {
            return $this->ms;
        }
    };
    app()->instance(VideoProbe::class, $this->probe);
});

function chayJob(Lesson $l): void
{
    $l->refresh();
    app()->call([new ReadVideoDuration($l->id, (string) $l->video_path), 'handle']);
}

it('AC-03-13 Tải bai4.mp4, Job đọc 150 giây; không nhập tay được thời lượng', function () {
    Queue::fake();
    $b4 = KhoaHoc::bai(KhoaHoc::chuong(KhoaHoc::banMo(KhoaHoc::giao($this->lan, $this->a))), 'Bài 4', LessonType::Video);
    $this->signIn($this->a)->post(route('teacher.lessons.video', $b4), ['video' => UploadedFile::fake()->create('bai4.mp4', 100, 'video/mp4')])->assertRedirect();
    Queue::assertPushed(ReadVideoDuration::class, fn ($j) => $j->lessonId === $b4->id);

    $this->probe->ms = 150_000;
    chayJob($b4);
    $this->patch(route('teacher.lessons.update', $b4), ['video_duration_ms' => 1])->assertRedirect();
    expect($b4->fresh()->video_duration_ms)->toBe(150_000);
});

it('AC-03-73 Video bắt buộc chưa có thời lượng: chặn gửi (E1); sau khi Job chạy gửi được', function () {
    $v = KhoaHoc::duDieuKien(KhoaHoc::giao($this->lan, $this->a), 1);
    $b3 = KhoaHoc::bai($v->sections()->first(), 'Bài 3', LessonType::Video, ['video_path' => 'lesson-videos/bai3.mp4']);
    Storage::disk('local')->put('lesson-videos/bai3.mp4', 'x');

    $r = $this->signIn($this->a)->postJson(route('teacher.course-versions.submit', $v))->assertStatus(422);
    expect($r->json('errors')['checklist.0'][0])->toContain('Bài 3')->toContain('chưa có thời lượng')
        ->and($v->fresh()->status)->toBe(CourseVersionStatus::Nhap);

    $this->probe->ms = 150_000;
    chayJob($b3);
    $this->postJson(route('teacher.course-versions.submit', $v))->assertRedirect();
    expect($v->fresh()->status)->toBe(CourseVersionStatus::ChoDuyet);
});

it('AC-03-81 Video không bắt buộc chưa có thời lượng: chặn theo A15 (không phải E1), chưa chụp', function () {
    $v = KhoaHoc::duDieuKien(KhoaHoc::giao($this->lan, $this->a), 1);
    $b9 = KhoaHoc::bai($v->sections()->first(), 'Bài 9', LessonType::Video, ['is_required' => false, 'video_path' => 'lesson-videos/bai9.mp4']);
    Storage::disk('local')->put('lesson-videos/bai9.mp4', 'x');

    $list = DraftChecklist::evaluate($v);
    expect(array_column($list, 'code'))->toBe(['A15']);
    $this->signIn($this->a)->postJson(route('teacher.course-versions.submit', $v))->assertStatus(422);
    expect($v->fresh()->status)->toBe(CourseVersionStatus::Nhap)->and(QuizQuestion::query()->whereNotNull('snapshot')->count())->toBe(0);

    $this->probe->ms = 90_000;
    chayJob($b9);
    $this->postJson(route('teacher.course-versions.submit', $v))->assertRedirect();
    expect($v->fresh()->status)->toBe(CourseVersionStatus::ChoDuyet);
});

it('DT-28 Job bỏ ghi khi bài đã trỏ tệp khác', function () {
    $b = KhoaHoc::bai(KhoaHoc::chuong(KhoaHoc::banMo(KhoaHoc::giao($this->lan, $this->a))), 'Bài 4', LessonType::Video, ['video_path' => 'lesson-videos/moi.mp4']);
    Storage::disk('local')->put('lesson-videos/cu.mp4', 'x');
    $this->probe->ms = 60_000;
    app()->call([new ReadVideoDuration($b->id, 'lesson-videos/cu.mp4'), 'handle']);
    expect($b->fresh()->video_duration_ms)->toBeNull();
});
