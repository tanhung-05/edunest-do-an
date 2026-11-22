<?php

namespace App\Http\Controllers\Academic;

use App\Actions\M27\Community;
use App\Http\Controllers\Controller;
use App\Models\M27\CommunityComment;
use App\Models\M27\CommunityPost;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** MH-GVU kiểm duyệt cộng đồng (QT-29 bước 6–9, A4): bài có báo cáo lên trước, ẩn/hiện lại có lý do. */
class CommunityModerationController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->query('filter') === 'hidden' ? 'hidden' : ($request->query('filter') === 'all' ? 'all' : 'reported');
        $reports = DB::table('community_reports')->selectRaw('community_post_id, count(*) as n')->groupBy('community_post_id')->pluck('n', 'community_post_id');
        $posts = CommunityPost::query()->with(['author:id,name', 'comments.author:id,name'])
            ->when($filter === 'reported', fn ($q) => $q->whereIn('id', $reports->keys()))
            ->when($filter === 'hidden', fn ($q) => $q->where(fn ($w) => $w->where('status', Community::DA_AN)
                ->orWhereHas('comments', fn ($c) => $c->where('status', Community::DA_AN))))
            ->orderByDesc('id')->limit(50)->get()
            ->sortByDesc(fn (CommunityPost $p) => (int) ($reports[$p->id] ?? 0))->values();
        $reasons = DB::table('community_reports')->whereIn('community_post_id', $posts->pluck('id'))->orderBy('id')
            ->get(['community_post_id', 'reason'])->groupBy('community_post_id');

        return Inertia::render('academic/community/index', [
            'filter' => $filter,
            'posts' => $posts->map(fn (CommunityPost $p) => [
                'id' => $p->id, 'title' => $p->title, 'body' => $p->body, 'author' => $p->author?->name,
                'status' => $p->status, 'hidden_reason' => $p->hidden_reason, 'created_at' => $p->created_at?->toIso8601String(),
                'image_url' => $p->image_path === null ? null : route('community.image', $p),
                'reports' => ($reasons->get($p->id) ?? collect())->pluck('reason')->values(),
                'comments' => $p->comments->map(fn (CommunityComment $c) => [
                    'id' => $c->id, 'author' => $c->author?->name, 'body' => $c->body, 'status' => $c->status, 'hidden_reason' => $c->hidden_reason,
                ])->values(),
            ]),
        ]);
    }

    public function post(Request $request, CommunityPost $post, Community $community): RedirectResponse
    {
        $data = $request->validate(['hide' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:500']]);
        $community->moderatePost($this->academic($request), $post, (bool) $data['hide'], (string) $data['reason']);

        return back()->with('status', $data['hide'] ? 'Đã ẩn bài.' : 'Đã hiện lại bài.');
    }

    public function comment(Request $request, CommunityComment $comment, Community $community): RedirectResponse
    {
        $data = $request->validate(['hide' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:500']]);
        $community->moderateComment($this->academic($request), $comment, (bool) $data['hide'], (string) $data['reason']);

        return back()->with('status', $data['hide'] ? 'Đã ẩn bình luận.' : 'Đã hiện lại bình luận.');
    }

    private function academic(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
