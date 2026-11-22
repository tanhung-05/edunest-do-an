<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\M17\Discussions;
use App\Enums\M17\DiscussionStatus;
use App\Http\Controllers\Controller;
use App\Models\M03\CourseAuthor;
use App\Models\M05\Lesson;
use App\Models\M17\Discussion;
use App\Models\M17\DiscussionReply;
use App\Models\User;
use App\Queries\M17\LessonCommunity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-GV-12: hàng chờ hỏi đáp của các khóa đang được giao; chưa trả lời, chờ lâu nhất trước (QT-18). */
final class DiscussionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->teacher($request);
        $courseIds = CourseAuthor::query()->where('user_id', $user->id)->whereNull('ended_at')->pluck('course_id');
        $tab = $request->string('tab')->toString() === 'answered' ? 'answered' : 'waiting';
        $rows = Discussion::query()->whereIn('course_id', $courseIds)->with(['user:id,name', 'course:id,title'])
            ->when($tab === 'waiting', fn ($q) => $q->where('status', DiscussionStatus::ChuaTraLoi)->orderBy('created_at')->orderBy('id'))
            ->when($tab === 'answered', fn ($q) => $q->whereIn('status', [DiscussionStatus::DaTraLoi, DiscussionStatus::DaChapNhan])->orderByDesc('updated_at'))
            ->limit(100)->get();
        $replies = DiscussionReply::query()->whereIn('discussion_id', $rows->pluck('id'))->with('user:id,name')->orderBy('id')->get()->groupBy('discussion_id');
        $lessons = Lesson::query()->whereIn('id', $rows->pluck('lesson_id'))->pluck('title', 'id');

        return Inertia::render('teacher/discussions/index', [
            'tab' => $tab,
            'waiting_count' => Discussion::query()->whereIn('course_id', $courseIds)->where('status', DiscussionStatus::ChuaTraLoi)->count(),
            'discussions' => $rows->map(fn (Discussion $d) => [
                ...LessonCommunity::row($d, $replies->get($d->id, collect()), $user, true, false, true),
                'course' => $d->course?->title,
                'lesson' => $lessons->get($d->lesson_id),
                'reply_url' => route('teacher.discussions.reply', $d),
            ])->all(),
        ]);
    }

    public function reply(Request $request, Discussion $discussion, Discussions $discussions): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $discussions->reply($this->teacher($request), $discussion, $data['body']);

        return back()->with('status', 'Đã gửi câu trả lời.');
    }

    public function accept(Request $request, Discussion $discussion, DiscussionReply $reply, Discussions $discussions): RedirectResponse
    {
        $discussions->accept($this->teacher($request), $discussion, $reply);

        return back()->with('status', 'Đã chấp nhận câu trả lời.');
    }

    public function unaccept(Request $request, Discussion $discussion, Discussions $discussions): RedirectResponse
    {
        $discussions->unaccept($this->teacher($request), $discussion);

        return back()->with('status', 'Đã bỏ chấp nhận.');
    }

    private function teacher(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
