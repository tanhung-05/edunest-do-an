<?php

namespace App\Support\M06;

use App\Enums\M03\SaleStatus;
use App\Models\M03\Course;
use App\Models\M05\CourseSection;
use App\Models\M05\Lesson;
use App\Models\M05\LessonResource;
use Illuminate\Support\Facades\URL;

/**
 * NH-18, QT-06 b9–10, E7, E15: phạm vi công khai. Khóa phải `DANG_BAN`/`NGUNG_BAN` có bản công bố (khác → 404);
 * bài phải thuộc bản công bố hiện hành, còn sống, có cờ xem thử (khác → 403). Kiểm lại ở mỗi lần đọc tệp.
 */
final class PublicLessonAccess
{
    public const URL_TTL_MINUTES = 15;

    public static function course(string $slug): Course
    {
        $course = Course::query()->where('slug', $slug)->first();
        abort_if($course === null || $course->current_version_id === null || $course->sale_status === SaleStatus::ChuaBan, 404);

        return $course;
    }

    public static function previewLesson(Course $course, int $lessonId): Lesson
    {
        $lesson = Lesson::query()->whereKey($lessonId)->first();
        $ok = $lesson !== null
            && $lesson->course_version_id === $course->current_version_id
            && $lesson->removed_at === null
            && $lesson->is_preview
            && CourseSection::query()->whereKey($lesson->course_section_id)->whereNull('removed_at')->exists();
        abort_unless($ok, 403, 'Bài này không mở xem thử.');

        /** @var Lesson $lesson */
        return $lesson;
    }

    public static function resource(Lesson $lesson, int $resourceId): LessonResource
    {
        $r = LessonResource::query()->whereKey($resourceId)->where('lesson_id', $lesson->id)->whereNull('removed_at')->first();
        abort_if($r === null, 403);

        return $r;
    }

    /**
     * URL ký 15 phút, không gắn phiên (CR-27, ADR-009).
     *
     * @param  array<string, mixed>  $params
     */
    public static function signed(string $route, array $params): string
    {
        return URL::temporarySignedRoute($route, now()->addMinutes(self::URL_TTL_MINUTES), $params);
    }
}
