<?php

namespace App\Jobs\M05;

use App\Models\M03\Course;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Support\M05\VideoProbe;
use App\Support\Tx;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * TV-07 (05 §2, DT-28): đọc metadata ngoài giao dịch; chỉ ghi khi bài vẫn trỏ đúng tệp đã đọc
 * (đường dẫn bất biến mỗi lần tải) và bản còn `NHAP`.
 */
class ReadVideoDuration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $lessonId, public string $videoPath)
    {
        $this->onQueue('media');
    }

    public function handle(VideoProbe $probe): void
    {
        if (! Storage::disk('local')->exists($this->videoPath)) {
            return;
        }
        $ms = $probe->durationMs(Storage::disk('local')->path($this->videoPath));
        if ($ms === null) {
            return;
        }
        Tx::run(function () use ($ms) {
            /** @var Lesson|null $lesson */
            $lesson = Lesson::query()->whereKey($this->lessonId)->first();
            if ($lesson === null) {
                return;
            }
            // Khóa courses → course_versions → bài (05 §1), rồi đọc lại đường dẫn hiện hành.
            $versionId = $lesson->course_version_id;
            $courseId = (int) CourseVersion::query()->whereKey($versionId)->value('course_id');
            Course::query()->whereKey($courseId)->lockForUpdate()->first();
            CourseVersion::query()->whereKey($versionId)->lockForUpdate()->first();
            $fresh = Lesson::query()->whereKey($this->lessonId)->lockForUpdate()->first();
            if ($fresh === null || $fresh->video_path !== $this->videoPath || $fresh->removed_at !== null) {
                return;
            }
            $fresh->forceFill(['video_duration_ms' => $ms])->save();
        });
    }
}
