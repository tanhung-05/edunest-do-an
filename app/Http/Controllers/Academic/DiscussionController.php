<?php

namespace App\Http\Controllers\Academic;

use App\Actions\M17\Discussions;
use App\Enums\M17\DiscussionStatus;
use App\Http\Controllers\Controller;
use App\Models\M05\Lesson;
use App\Models\M17\Discussion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** QT-18 HD-4: Giáo vụ ẩn/hiện câu hỏi hỏi đáp ở mọi khóa, có lý do (02c §3 `discussion.hide`). */
final class DiscussionController extends Controller
{
    public function index(Request $request): Response
    {
        $hidden = $request->string('tab')->toString() === 'hidden';
        $rows = Discussion::query()->with(['user:id,name', 'course:id,title'])
            ->when($hidden, fn ($q) => $q->where('status', DiscussionStatus::DaAn), fn ($q) => $q->where('status', '!=', DiscussionStatus::DaAn))
            ->orderByDesc('id')->limit(100)->get();
        $lessons = Lesson::query()->whereIn('id', $rows->pluck('lesson_id'))->pluck('title', 'id');

        return Inertia::render('academic/discussions/index', [
            'tab' => $hidden ? 'hidden' : 'visible',
            'discussions' => $rows->map(fn (Discussion $d) => [
                'id' => $d->id, 'body' => $d->body, 'author' => $d->user?->name, 'course' => $d->course?->title,
                'lesson' => $lessons->get($d->lesson_id), 'status_label' => $d->status->label(), 'reason' => $d->hidden_reason,
                'created_at' => $d->created_at?->toIso8601String(),
                'hide_url' => $hidden ? null : route('academic.discussions.hide', $d),
                'unhide_url' => $hidden ? route('academic.discussions.unhide', $d) : null,
            ])->all(),
        ]);
    }

    public function hide(Request $request, Discussion $discussion, Discussions $discussions): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $discussions->hide($this->actor($request), $discussion, $data['reason']);

        return back()->with('status', 'Đã ẩn câu hỏi.');
    }

    public function unhide(Request $request, Discussion $discussion, Discussions $discussions): RedirectResponse
    {
        $discussions->unhide($this->actor($request), $discussion);

        return back()->with('status', 'Đã hiện lại câu hỏi.');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
