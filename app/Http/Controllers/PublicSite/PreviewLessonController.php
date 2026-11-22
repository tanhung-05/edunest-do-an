<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\M05\LessonType;
use App\Http\Controllers\Controller;
use App\Models\M05\CourseSection;
use App\Models\M05\LessonResource;
use App\Support\M06\PublicLessonAccess;
use App\Support\M09\SafeFileResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * MH-KH-04 (QT-06 b8–11, A5, E7, E15): `ReadPublicPreviewLesson` cấp URL ký 15 phút;
 * `StreamPreviewLessonResource` kiểm lại phạm vi ở mỗi GET/Range và chặn cache chia sẻ (ADR-009).
 */
class PreviewLessonController extends Controller
{
    public function show(string $slug, int $lesson): Response
    {
        $course = PublicLessonAccess::course($slug);
        $l = PublicLessonAccess::previewLesson($course, $lesson);
        $params = ['slug' => $course->slug, 'lesson' => $l->id];

        return Inertia::render('public/lessons/preview', [
            'course' => ['title' => $course->title, 'slug' => $course->slug, 'sale_status_label' => $course->sale_status->label()],
            'section' => CourseSection::query()->whereKey($l->course_section_id)->value('title'),
            'lesson' => [
                'id' => $l->id,
                'title' => $l->title,
                'type' => $l->type->value,
                'type_label' => $l->type->label(),
                'objectives' => $l->objectives,
                'content' => $l->type === LessonType::BaiDoc ? $l->content : null,
                'minutes' => $l->video_duration_ms !== null ? (int) round($l->video_duration_ms / 60000) : null,
                'video_url' => $l->type === LessonType::Video && $l->video_path !== null ? PublicLessonAccess::signed('public.lessons.preview.video', $params) : null,
                'pdf_url' => $l->type === LessonType::Pdf && $l->pdf_path !== null ? PublicLessonAccess::signed('public.lessons.preview.pdf', $params) : null,
            ],
            'resources' => LessonResource::query()->where('lesson_id', $l->id)->whereNull('removed_at')->orderBy('position')->get()
                ->map(fn (LessonResource $r) => ['id' => $r->id, 'title' => $r->title, 'url' => PublicLessonAccess::signed('public.lessons.preview.resources.show', [...$params, 'resource' => $r->id])])
                ->values(),
        ]);
    }

    public function video(string $slug, int $lesson): BinaryFileResponse
    {
        $l = PublicLessonAccess::previewLesson(PublicLessonAccess::course($slug), $lesson);
        abort_if($l->type !== LessonType::Video || $l->video_path === null, 404);

        return $this->stream((string) $l->video_path, 'video/mp4');
    }

    public function pdf(string $slug, int $lesson): BinaryFileResponse
    {
        $l = PublicLessonAccess::previewLesson(PublicLessonAccess::course($slug), $lesson);
        abort_if($l->type !== LessonType::Pdf || $l->pdf_path === null, 404);

        return $this->stream((string) $l->pdf_path, 'application/pdf');
    }

    public function resource(string $slug, int $lesson, int $resource): BinaryFileResponse
    {
        $l = PublicLessonAccess::previewLesson(PublicLessonAccess::course($slug), $lesson);
        $r = PublicLessonAccess::resource($l, $resource);

        return $this->stream($r->file_path, $r->mime_type, $r->original_name);
    }

    private function stream(string $path, string $mime, ?string $downloadName = null): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        return SafeFileResponse::make(Storage::disk('local')->path($path), $mime, $downloadName);
    }
}
