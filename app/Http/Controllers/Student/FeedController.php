<?php

namespace App\Http\Controllers\Student;

use App\Actions\M27\Community;
use App\Enums\M01\RoleCode;
use App\Enums\M03\SaleStatus;
use App\Enums\M27\PostTopic;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M27\CommunityComment;
use App\Models\M27\CommunityPost;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** MH-HV cộng đồng (QT-29): bảng tin chung, chi tiết bài, đăng, sửa, bình luận, thích, báo cáo. */
class FeedController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->student($request);
        $topic = PostTopic::tryFrom((string) $request->query('topic', ''));
        $posts = CommunityPost::query()->with(['author:id,name', 'course:id,title'])
            ->where(fn ($q) => $q->where('status', Community::HIEN)->orWhere('user_id', $user->id))
            ->when($topic, fn ($q, $t) => $q->where('topic', $t->value))
            ->orderByDesc('id')->paginate(15)->withQueryString();
        $ids = $posts->getCollection()->pluck('id');
        $comments = CommunityComment::query()->whereIn('community_post_id', $ids)->where('status', Community::HIEN)
            ->selectRaw('community_post_id, count(*) as n')->groupBy('community_post_id')->pluck('n', 'community_post_id');
        $liked = DB::table('community_likes')->where('user_id', $user->id)->whereIn('community_post_id', $ids)->pluck('community_post_id')->flip();

        return Inertia::render('student/community/index', [
            'topic' => $topic?->value,
            'topics' => array_map(fn (PostTopic $t) => ['value' => $t->value, 'label' => $t->label()], PostTopic::cases()),
            'courses' => Course::query()->where('sale_status', SaleStatus::DangBan->value)->orderBy('title')->get(['id', 'title'])
                ->map(fn (Course $c) => ['id' => $c->id, 'name' => $c->title])->values(),
            'posts' => $posts->through(fn (CommunityPost $p) => [
                ...$this->card($p, $user),
                'excerpt' => Str::limit($p->body, 280),
                'comment_count' => $p->status === Community::HIEN ? (int) ($comments[$p->id] ?? 0) : 0,
                'liked' => isset($liked[$p->id]),
            ]),
            // E47: bình luận bị ẩn riêng của chính mình, chỉ lộ nội dung và lý do của mình.
            'my_hidden_comments' => CommunityComment::query()->where('user_id', $user->id)->where('status', Community::DA_AN)
                ->orderByDesc('id')->limit(20)->get()
                ->map(fn (CommunityComment $c) => ['id' => $c->id, 'body' => Str::limit($c->body, 200), 'reason' => $c->hidden_reason])->values(),
        ]);
    }

    public function show(Request $request, CommunityPost $post): Response
    {
        $user = $this->student($request);
        abort_unless(Community::canRead($user, $post), 404);
        $post->load(['author:id,name', 'course:id,title']);
        $comments = $post->status !== Community::HIEN ? collect() : CommunityComment::query()->with('author:id,name')
            ->where('community_post_id', $post->id)
            ->where(fn ($q) => $q->where('status', Community::HIEN)->orWhere('user_id', $user->id))->orderBy('id')->get();

        return Inertia::render('student/community/show', [
            'post' => [
                ...$this->card($post, $user),
                'body' => $post->body,
                'liked' => DB::table('community_likes')->where('user_id', $user->id)->where('community_post_id', $post->id)->exists(),
                'reported' => DB::table('community_reports')->where('user_id', $user->id)->where('community_post_id', $post->id)->exists(),
                'can_edit' => $post->user_id === $user->id && $post->status === Community::HIEN && $user->hasVerifiedEmail(),
            ],
            'comments' => $comments->map(fn (CommunityComment $c) => [
                'id' => $c->id, 'author' => $c->author?->name, 'body' => $c->body, 'created_at' => $c->created_at?->toIso8601String(),
                'hidden_reason' => $c->status === Community::DA_AN ? $c->hidden_reason : null,
            ])->values(),
        ]);
    }

    public function store(Request $request, Community $community): RedirectResponse
    {
        $data = $request->validate([
            'topic' => ['required', Rule::enum(PostTopic::class)],
            'course_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
        $post = $community->post($this->student($request), [
            'topic' => PostTopic::from((string) $data['topic']), 'course_id' => isset($data['course_id']) ? (int) $data['course_id'] : null,
            'title' => (string) $data['title'], 'body' => (string) $data['body'],
        ], $request->file('image'), CarbonImmutable::now());

        return to_route('student.feed.show', $post)->with('status', 'Đã đăng bài.');
    }

    public function update(Request $request, CommunityPost $post, Community $community): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
        $community->edit($this->student($request), $post, ['title' => (string) $data['title'], 'body' => (string) $data['body']],
            $request->file('image'), CarbonImmutable::now());

        return back()->with('status', 'Đã lưu bài.');
    }

    public function comment(Request $request, CommunityPost $post, Community $community): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $community->comment($this->student($request), $post, (string) $data['body']);

        return back()->with('status', 'Đã gửi bình luận.');
    }

    public function like(Request $request, CommunityPost $post, Community $community): RedirectResponse
    {
        $data = $request->validate(['liked' => ['required', 'boolean']]);
        $community->like($this->student($request), $post, (bool) $data['liked']);

        return back();
    }

    public function report(Request $request, CommunityPost $post, Community $community): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $new = $community->report($this->student($request), $post, (string) $data['reason']);

        return back()->with('status', $new ? 'Đã gửi báo cáo cho giáo vụ.' : 'Bạn đã báo cáo bài này trước đó.');
    }

    /** E47: ảnh của bài theo cùng quyền đọc bài; Giáo vụ xem trong kiểm duyệt. */
    public function image(Request $request, CommunityPost $post): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(Community::canRead($user, $post) || ($user->isActive() && $user->hasEffectiveRole(RoleCode::GiaoVu)), 404);
        $disk = Storage::disk('local');
        abort_if($post->image_path === null || ! $disk->exists($post->image_path), 404);

        return response()->file($disk->path($post->image_path), ['Cache-Control' => 'private, no-store']);
    }

    /** @return array<string, mixed> */
    private function card(CommunityPost $p, User $user): array
    {
        return [
            'id' => $p->id,
            'title' => $p->title,
            'topic' => $p->topic->label(),
            'course' => $p->course?->title,
            'author' => $p->author?->name,
            'created_at' => $p->created_at?->toIso8601String(),
            'edited' => $p->edited_at !== null,
            'like_count' => $p->like_count,
            'is_mine' => $p->user_id === $user->id,
            'hidden_reason' => $p->status === Community::DA_AN ? $p->hidden_reason : null,
            'image_url' => $p->image_path === null ? null : route('community.image', $p),
        ];
    }

    private function student(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
