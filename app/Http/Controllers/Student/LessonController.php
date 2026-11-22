<?php

namespace App\Http\Controllers\Student;

use App\Actions\M09\FinishLesson;
use App\Actions\M09\MarkLessonCompleted;
use App\Actions\M09\RecordWatchBeat;
use App\Enums\M05\LessonType;
use App\Enums\M09\Activity;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M05\Lesson;
use App\Models\M05\LessonResource;
use App\Models\M09\LessonProgress;
use App\Models\M11\Setting;
use App\Models\User;
use App\Queries\M09\LearningEntry;
use App\Queries\M12\EnrollmentOverview;
use App\Queries\M17\LessonCommunity;
use App\Support\M03\CourseCoverUrl;
use App\Support\M09\LearningAccess;
use App\Support\M09\LearningFile;
use App\Support\M09\LearningLocks;
use App\Support\M09\SafeFileResponse;
use App\Support\M14\RatingView;
use App\Support\Tx;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class LessonController extends Controller
{
    public function show(Request $request, Course $course, Lesson $lesson): Response
    {
        $user = $this->student($request);
        $enrollment = LearningEntry::enrollment($user, $course);
        $progress = Tx::run(function () use ($user, $enrollment, $lesson) {
            [$user, $enrollment] = LearningLocks::student($user, $enrollment);
            LearningAccess::authorize($user, $enrollment, Activity::ViewLesson, $lesson);
            $progress = FinishLesson::progress($enrollment, $lesson, now());
            // Video dùng giờ nhận mốc xem để chọn bài tiếp tục học (QT-09 bước 8).
            if ($lesson->type !== LessonType::Video) {
                $progress->last_viewed_at = now();
                $progress->save();
            }

            return $progress;
        });

        $params = ['course' => $course->id, 'lesson' => $lesson->id];
        $file = fn (string $id): string => LearningFile::signed($request, 'student.lessons.files.stream', [...$params, 'file' => $id]);
        $quiz = $lesson->quiz;
        $completed = LessonProgress::query()->where('enrollment_id', $enrollment->id)->whereNotNull('completed_at')->pluck('lesson_id')->flip();

        return Inertia::render('student/lessons/show', [
            'course' => $course->title,
            // Thông tin phụ cho trình học v5: đường dẫn, ảnh bìa, giáo viên, quiz của bài.
            'course_meta' => [
                'id' => $course->id,
                'teacher' => $course->currentAuthor?->user?->name,
                'cover' => CourseCoverUrl::for($course),
            ],
            'quiz' => $quiz === null ? null : [
                'title' => $quiz->title,
                'pass_percent' => $quiz->pass_percent,
                'duration_minutes' => $quiz->duration_minutes,
                'max_attempts' => $quiz->max_attempts,
            ],
            'has_new_version' => $course->current_version_id !== null && $course->current_version_id !== $enrollment->course_version_id,
            'progress' => EnrollmentOverview::progress($enrollment),
            // P8 M25: lối vào hỏi AI trong bài khi cờ AI bật.
            'ai_url' => Setting::valueFor('ai.enabled') === true ? route('student.lessons.ai.show', $params) : null,
            // P6: hỏi đáp, ghi chú (QT-18) và chuyển phiên bản (QT-21).
            'community' => LessonCommunity::forStudent($user, $enrollment, $lesson),
            'switch_version_url' => $course->current_version_id !== null && $course->current_version_id !== $enrollment->course_version_id
                ? route('student.enrollments.switch-version', $enrollment) : null,
            // MH-HV-03 tab Tổng quan: biểu mẫu đánh giá khóa (QT-16).
            'rating' => RatingView::forCourse($course, $user, $enrollment),
            'lesson' => ['title' => $lesson->title, 'type' => $lesson->type->value, 'objectives' => $lesson->objectives,
                // HTML đã lọc lúc ghi (HtmlSanitizer, L15): giữ liên kết và ký hiệu.
                'content' => $lesson->type === LessonType::BaiDoc ? $lesson->content : null],
            'position_ms' => $progress->last_position_ms,
            'completed' => $progress->completed_at !== null,
            'video_url' => $lesson->type === LessonType::Video && $lesson->video_path !== null ? $file('video') : null,
            'pdf_url' => $lesson->type === LessonType::Pdf && $lesson->pdf_path !== null ? $file('pdf') : null,
            'resources' => $lesson->resources()->get()->map(fn (LessonResource $resource) => ['title' => $resource->title, 'url' => $file((string) $resource->id)]),
            'watch_url' => route('student.lessons.watch-beats.store', $params),
            'complete_url' => route('student.lessons.complete', $params),
            'quiz_url' => $quiz === null ? null : route('student.quizzes.show', ['course' => $course->id, 'quiz' => $quiz->id]),
            'lessons' => LearningAccess::lessons($enrollment)->map(fn (Lesson $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'section_id' => $item->course_section_id,
                'section' => $item->section->title,
                'type' => $item->type->value,
                'duration_ms' => $item->video_duration_ms,
                'required' => $item->is_required,
                'completed' => $completed->has($item->id),
                'url' => LearningAccess::allows($user, $enrollment, Activity::ViewLesson, $item)
                    ? route('student.lessons.show', ['course' => $course->id, 'lesson' => $item->id]) : null,
            ]),
        ]);
    }

    public function stream(Request $request, Course $course, Lesson $lesson, string $file): BinaryFileResponse
    {
        LearningFile::verify($request);
        $user = $this->student($request);
        LearningAccess::authorize($user, LearningEntry::enrollment($user, $course), Activity::ViewLesson, $lesson);
        $resource = null;
        if ($file === 'video') {
            $path = $lesson->type === LessonType::Video ? $lesson->video_path : null;
            $mime = 'video/mp4';
        } elseif ($file === 'pdf') {
            $path = $lesson->type === LessonType::Pdf ? $lesson->pdf_path : null;
            $mime = 'application/pdf';
        } else {
            abort_unless(ctype_digit($file), 404);
            $resource = $lesson->resources()->findOrFail((int) $file);
            $path = $resource->file_path;
            $mime = $resource->mime_type;
        }
        $disk = Storage::disk('local');
        abort_if($path === null || ! $disk->exists($path), 404);

        return SafeFileResponse::make($disk->path($path), $mime, $resource?->original_name);
    }

    public function watch(Request $request, Course $course, Lesson $lesson, RecordWatchBeat $record): JsonResponse
    {
        $at = now();
        $data = $request->validate(['position_ms' => ['required', 'integer', 'min:0']]);
        $user = $this->student($request);
        $result = $record->handle($user, LearningEntry::enrollment($user, $course), $lesson, (int) $data['position_ms'], $at);

        return response()->json([
            'counted' => $result['counted'],
            'completed' => $result['progress']->completed_at !== null,
            'valid_watch_ms' => $result['progress']->valid_watch_ms,
            'position_ms' => $result['progress']->last_position_ms,
        ]);
    }

    public function complete(Request $request, Course $course, Lesson $lesson, MarkLessonCompleted $complete): RedirectResponse
    {
        $user = $this->student($request);
        $complete->handle($user, LearningEntry::enrollment($user, $course), $lesson);

        return to_route('student.lessons.show', ['course' => $course->id, 'lesson' => $lesson->id]);
    }

    private function student(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
