<?php

namespace App\Http\Controllers\Student;

use App\Actions\M25\AiAssistant;
use App\Enums\M08\EnrollmentValidity;
use App\Enums\M09\Activity;
use App\Enums\M25\AiKind;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M05\Lesson;
use App\Models\M11\Setting;
use App\Models\M25\AiConversation;
use App\Models\M25\AiMessage;
use App\Models\User;
use App\Queries\M09\LearningEntry;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M09\LearningAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-HV AI trong bài (QT-27b): hỏi AI theo nội dung bài, xem lịch sử (A3), chuyển câu hỏi cho Giáo viên (bước 9–10). */
class AiLessonController extends Controller
{
    public function show(Request $request, Course $course, Lesson $lesson): Response
    {
        $user = $this->student($request);
        $enrollment = LearningEntry::enrollment($user, $course);
        LearningAccess::authorize($user, $enrollment, Activity::ViewLesson, $lesson);
        $conversation = AiConversation::query()->where('kind', AiKind::BaiHoc->value)->where('user_id', $user->id)
            ->where('enrollment_id', $enrollment->id)->where('lesson_id', $lesson->id)->first();
        $limit = AiAssistant::limit('ai.daily_limit_user');

        return Inertia::render('student/lessons/ai', [
            'course' => ['id' => $course->id, 'title' => $course->title],
            'lesson' => ['id' => $lesson->id, 'title' => $lesson->title],
            'enabled' => Setting::valueFor('ai.enabled') === true,
            'can_ask' => EnrollmentValidityChecker::for($enrollment) === EnrollmentValidity::ConHan,
            'remaining' => max(0, $limit - AiAssistant::usedToday('u:'.$user->id)),
            'limit' => $limit,
            'messages' => $conversation === null ? [] : $conversation->messages()->get()->map(fn (AiMessage $m) => [
                'id' => $m->id, 'role' => $m->role, 'content' => $m->content, 'status' => $m->status,
                'found' => $m->meta['found'] ?? null,
            ])->values(),
            'conversation_id' => $conversation?->id,
            'handed_off' => $conversation !== null && $conversation->discussion_id !== null,
            'ask_url' => route('student.lessons.ai.ask', ['course' => $course->id, 'lesson' => $lesson->id]),
            'hand_off_url' => $conversation === null ? null : route('student.ai.hand-off', $conversation),
            'lesson_url' => route('student.lessons.show', ['course' => $course->id, 'lesson' => $lesson->id]),
        ]);
    }

    public function ask(Request $request, Course $course, Lesson $lesson, AiAssistant $ai): RedirectResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:1000'], 'request_key' => ['required', 'string', 'max:64']]);
        $user = $this->student($request);
        $reply = $ai->askLesson($user, LearningEntry::enrollment($user, $course), $lesson, $data['question'], $data['request_key']);

        return back()->with('status', $reply->status === 'OK' ? 'AI đã trả lời.' : 'AI đang gặp lỗi, lượt hỏi đã được tính.');
    }

    public function handOff(Request $request, AiConversation $conversation, AiAssistant $ai): RedirectResponse
    {
        $ai->handOff($this->student($request), $conversation);

        return back()->with('status', 'Đã chuyển câu hỏi cho giáo viên. Xem trả lời ở mục Hỏi đáp của bài.');
    }

    private function student(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
