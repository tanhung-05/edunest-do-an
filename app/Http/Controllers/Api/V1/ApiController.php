<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\M09\MarkLessonCompleted;
use App\Actions\M21\XpLedger;
use App\Actions\M28\ApiTokens;
use App\Enums\M08\EnrollmentValidity;
use App\Enums\M09\Activity;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M09\LessonProgress;
use App\Models\User;
use App\Queries\M09\LearningEntry;
use App\Queries\M12\EnrollmentOverview;
use App\Support\DomainRejection;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M09\LearningAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API v1 cho ứng dụng di động (M28, tài liệu `docs/thiet-ke/api-v1.md`): token gắn thiết bị, khóa của tôi, bài học,
 * đánh dấu hoàn thành, thông báo. Mọi quyền đi qua cùng Action/Policy của web.
 */
final class ApiController extends Controller
{
    public function token(Request $request, ApiTokens $tokens): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        try {
            $issued = $tokens->issue((string) $data['email'], (string) $data['password'], (string) $data['device_name']);
        } catch (DomainRejection $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json($issued, 201);
    }

    public function logout(Request $request, ApiTokens $tokens): JsonResponse
    {
        $user = $this->user($request);
        $tokens->revoke($user, $user->currentAccessToken());

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(['data' => [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(), 'streak_days' => XpLedger::currentStreak($user->id),
        ]]);
    }

    public function courses(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $rows = Enrollment::query()->with('course:id,title,slug')->where('user_id', $user->id)->orderByDesc('id')->get();

        return response()->json(['data' => $rows->map(fn (Enrollment $e) => [
            'course_id' => $e->course_id,
            'title' => $e->course?->title,
            'validity' => EnrollmentValidityChecker::for($e)->value,
            'expires_at' => $e->het_han_luc?->toIso8601String(),
            'progress' => EnrollmentOverview::progress($e),
        ])->values()]);
    }

    public function lessons(Request $request, Course $course): JsonResponse
    {
        $user = $this->user($request);
        $enrollment = LearningEntry::enrollment($user, $course);
        $done = LessonProgress::query()->where('enrollment_id', $enrollment->id)->whereNotNull('completed_at')->pluck('lesson_id')->flip();

        return response()->json(['data' => LearningAccess::lessons($enrollment)->map(fn (Lesson $l) => [
            'id' => $l->id, 'title' => $l->title, 'type' => $l->type->value, 'required' => $l->is_required,
            'completed' => isset($done[$l->id]),
            'open' => LearningAccess::allows($user, $enrollment, Activity::ViewLesson, $l),
        ])->values()]);
    }

    public function lesson(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = $this->user($request);
        $enrollment = LearningEntry::enrollment($user, $course);
        LearningAccess::authorize($user, $enrollment, Activity::ViewLesson, $lesson);

        return response()->json(['data' => [
            'id' => $lesson->id, 'title' => $lesson->title, 'type' => $lesson->type->value,
            'objectives' => $lesson->objectives,
            // HTML đã lọc lúc ghi (L15). Video, PDF chỉ phát trên web có watermark và gắn thiết bị trình duyệt.
            'content_html' => $lesson->content,
            'web_url' => route('student.lessons.show', ['course' => $course->id, 'lesson' => $lesson->id]),
            'can_write' => EnrollmentValidityChecker::for($enrollment) === EnrollmentValidity::ConHan,
        ]]);
    }

    public function complete(Request $request, Course $course, Lesson $lesson, MarkLessonCompleted $complete): JsonResponse
    {
        $user = $this->user($request);
        try {
            $progress = $complete->handle($user, LearningEntry::enrollment($user, $course), $lesson);
        } catch (DomainRejection $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['data' => ['lesson_id' => $lesson->id, 'completed_at' => $progress->completed_at?->toIso8601String()]]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(['data' => $user->notifications()->latest()->limit(50)->get()->map(fn ($n) => [
            'id' => $n->id, 'type' => $n->type, 'data' => $n->data, 'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ])->values()]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
