<?php

use App\Actions\M27\Community;
use App\Enums\M01\RoleCode;
use App\Enums\M27\PostTopic;
use App\Models\M11\Setting;
use App\Models\M27\CommunityComment;
use App\Models\M27\CommunityPost;
use App\Models\User;
use App\Support\DomainRejection;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

pest()->group('P8');

function cdKetQua(callable $fn): string
{
    try {
        $fn();
    } catch (DomainRejection $e) {
        return $e->status === 403 ? 'forbidden' : 'rejected';
    }

    return 'ok';
}

function cdHv(string $name, bool $verified = true): User
{
    $u = taiKhoan($name.'-cd@edunest.test', RoleCode::HocVien);
    $u->forceFill(['email_verified_at' => $verified ? now() : null])->save();

    return $u->refresh();
}

function cdBai(User $u, string $title = 'Mẹo ôn hóa', ?CarbonImmutable $at = null): CommunityPost
{
    return app(Community::class)->post($u, ['topic' => PostTopic::OnThi, 'title' => $title, 'body' => 'Nội dung chia sẻ.'], null, $at ?? CarbonImmutable::now());
}

beforeEach(function () {
    Storage::fake('local');
    $this->c = app(Community::class);
    $this->a = cdHv('an');
    $this->b = cdHv('binh', false);
    $this->gv = taiKhoan('gvu-cd@edunest.test', RoleCode::GiaoVu);
});

it('BR-DG-24 BR-DG-28 CR-100 X2 Đăng bài có ảnh; vượt số bài trong 60 phút bị chặn; vai khác bị từ chối', function () {
    Setting::query()->updateOrCreate(['key' => 'community.posts_per_hour'], ['value' => 2]);
    $p = $this->c->post($this->a, ['topic' => PostTopic::HocTap, 'title' => 'Ảnh sơ đồ', 'body' => 'Xem ảnh.'],
        UploadedFile::fake()->image('so-do.png'), CarbonImmutable::parse('2026-12-01 09:00'));
    expect($p->status)->toBe('HIEN')->and(Storage::disk('local')->exists($p->image_path))->toBeTrue();
    cdBai($this->a, 'Bài 2', CarbonImmutable::parse('2026-12-01 09:10'));
    expect(cdKetQua(fn () => cdBai($this->a, 'Bài 3', CarbonImmutable::parse('2026-12-01 09:30'))))->toBe('rejected')
        ->and(cdKetQua(fn () => cdBai($this->a, 'Bài 3', CarbonImmutable::parse('2026-12-01 10:05'))))->toBe('ok')
        ->and(cdKetQua(fn () => cdBai(taiKhoan('gv-cd@edunest.test', RoleCode::GiaoVien))))->toBe('forbidden');
});

it('A2 A3 X3 BR-DG-29 Thích theo cặp, gửi lại không đảo; một báo cáo mỗi người, bài giữ hiển thị', function () {
    $p = cdBai($this->a);
    $this->c->like($this->b, $p, true);
    $this->c->like($this->b, $p, true);
    expect($p->fresh()->like_count)->toBe(1);
    $this->c->like($this->b, $p, false);
    expect($p->fresh()->like_count)->toBe(0);

    expect($this->c->report($this->b, $p, 'Spam'))->toBeTrue()
        ->and($this->c->report($this->b, $p, 'Spam lần 2'))->toBeFalse()
        ->and($this->c->report(cdHv('chi'), $p, 'Lạc đề'))->toBeTrue()
        ->and(DB::table('community_reports')->count())->toBe(2)->and($p->fresh()->status)->toBe('HIEN');
});

it('A1 CR-177 Chỉ chủ bài đã xác minh sửa bài đang hiện; giữ thích và báo cáo', function () {
    $p = cdBai($this->a);
    $this->c->like($this->b, $p, true);
    $edited = $this->c->edit($this->a, $p, ['title' => 'Mẹo ôn hóa (sửa)', 'body' => 'Bổ sung.'], null, CarbonImmutable::now());
    expect($edited->title)->toBe('Mẹo ôn hóa (sửa)')->and($edited->edited_at)->not->toBeNull()->and($edited->like_count)->toBe(1)
        ->and(cdKetQua(fn () => $this->c->edit($this->b, $p, ['title' => 'x', 'body' => 'y'], null, CarbonImmutable::now())))->toBe('forbidden');
    $pb = cdBai($this->b);
    expect(cdKetQua(fn () => $this->c->edit($this->b, $pb, ['title' => 'x', 'body' => 'y'], null, CarbonImmutable::now())))->toBe('forbidden');
    $this->c->moderatePost($this->gv, $p, true, 'Vi phạm');
    expect(cdKetQua(fn () => $this->c->edit($this->a, $p, ['title' => 'x', 'body' => 'y'], null, CarbonImmutable::now())))->toBe('rejected');
});

it('BR-DG-26 A4 X4 CR-64 Ẩn và hiện lại cần lý do, có nhật ký; ẩn rồi thì không bình luận, thích, báo cáo', function () {
    $p = cdBai($this->a);
    expect(cdKetQua(fn () => $this->c->moderatePost($this->gv, $p, true, '  ')))->toBe('rejected')
        ->and(cdKetQua(fn () => $this->c->moderatePost($this->b, $p, true, 'x')))->toBe('forbidden');
    $this->c->moderatePost($this->gv, $p, true, 'Quảng cáo');
    expect($p->fresh()->hidden_reason)->toBe('Quảng cáo')
        ->and(cdKetQua(fn () => $this->c->moderatePost($this->gv, $p, true, 'Lặp')))->toBe('rejected')
        ->and(cdKetQua(fn () => $this->c->comment($this->b, $p, 'Chào')))->toBe('rejected')
        ->and(cdKetQua(fn () => $this->c->like($this->b, $p, true)))->toBe('rejected')
        ->and(cdKetQua(fn () => $this->c->report($this->b, $p, 'x')))->toBe('rejected');
    $this->c->moderatePost($this->gv, $p, false, 'Đã xem lại');
    expect($p->fresh()->status)->toBe('HIEN')->and($p->fresh()->hidden_reason)->toBeNull()
        ->and(DB::table('audit_logs')->whereIn('action', ['COMMUNITY_POST_HIDE', 'COMMUNITY_POST_UNHIDE'])->count())->toBe(2);
});

it('CR-135 CR-175 E47 Bài ẩn: người khác 0 nội dung cả link trực tiếp và ảnh; tác giả thấy lý do; hiện lại chỉ công khai bình luận đang hiện', function () {
    $p = $this->c->post($this->a, ['topic' => PostTopic::HocTap, 'title' => 'Có ảnh', 'body' => 'Nội dung.'], UploadedFile::fake()->image('a.png'), CarbonImmutable::now());
    $c1 = $this->c->comment($this->b, $p, 'Bình luận 1');
    $this->c->comment($this->b, $p, 'Bình luận 2');
    $this->c->moderateComment($this->gv, $c1, true, 'Không phù hợp');

    // Học viên chưa xác minh vẫn đọc được; bình luận ẩn riêng chỉ người viết thấy kèm lý do.
    $this->signIn($this->b)->get(route('student.feed.show', $p))->assertInertia(fn ($x) => $x->has('comments', 2)
        ->where('comments.0.hidden_reason', 'Không phù hợp'));
    $chi = cdHv('chi');
    $this->signIn($chi)->get(route('student.feed.show', $p))->assertInertia(fn ($x) => $x->has('comments', 1));

    $this->c->moderatePost($this->gv, $p, true, 'Sai chủ đề');
    $dung = cdHv('dung');
    $this->signIn($dung)->get(route('student.feed.show', $p))->assertNotFound();
    $this->signIn($dung)->get(route('community.image', $p))->assertNotFound();
    $this->signIn($dung)->get(route('student.feed.index'))->assertInertia(fn ($x) => $x->has('posts.data', 0));
    $this->signIn($this->a)->get(route('student.feed.show', $p))->assertInertia(fn ($x) => $x->where('post.hidden_reason', 'Sai chủ đề')->has('comments', 0));
    $this->signIn($this->gv)->get(route('community.image', $p))->assertOk();
    $this->signIn($this->b)->get(route('student.feed.index'))->assertInertia(fn ($x) => $x->has('my_hidden_comments', 1)
        ->where('my_hidden_comments.0.reason', 'Không phù hợp'));

    $this->c->moderatePost($this->gv, $p, false, 'Đã sửa');
    $this->signIn($chi)->get(route('student.feed.show', $p))->assertInertia(fn ($x) => $x->has('comments', 1));
    expect(CommunityComment::query()->where('status', 'DA_AN')->count())->toBe(1);
});

it('CR-175 X1 Khách và vai không phải Học viên không đọc bảng tin; Giáo vụ xem hàng kiểm duyệt có báo cáo', function () {
    $p = cdBai($this->a);
    $this->c->report($this->b, $p, 'Spam');
    $this->get(route('student.feed.show', $p))->assertRedirect(route('login'));
    $this->signIn(taiKhoan('gv2-cd@edunest.test', RoleCode::GiaoVien))->get(route('student.feed.index'))->assertForbidden();
    $this->signIn($this->a)->post(route('student.feed.comments.store', $p), ['body' => 'Cảm ơn'])->assertRedirect();
    expect(CommunityComment::query()->count())->toBe(1);
    $this->signIn($this->gv)->get(route('academic.community.index'))->assertInertia(fn ($x) => $x->has('posts', 1)->has('posts.0.reports', 1));
    $this->signIn($this->gv)->post(route('academic.community.posts.moderate', $p), ['hide' => true, 'reason' => 'Spam'])->assertRedirect();
    expect($p->fresh()->status)->toBe('DA_AN');
});
