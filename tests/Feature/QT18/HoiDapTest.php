<?php

use App\Actions\M17\Discussions;
use App\Actions\M17\LessonNotes;
use App\Enums\M01\RoleCode;
use App\Enums\M15\NotificationKind;
use App\Enums\M17\DiscussionStatus;
use App\Models\M17\Discussion;
use App\Models\M17\DiscussionReply;
use App\Models\M17\LessonNote;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M09\LearningFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;

pest()->group('P6');

/** Đăng nhập học viên khác trong cùng phiên thử: bỏ gắn thiết bị của người trước. */
function hv(object $t, ?User $u = null)
{
    session()->forget(LearningFile::DEVICE);
    session()->save();

    return $t->h->signIn($t, $u);
}

beforeEach(function () {
    Storage::fake('local');
    $this->h = new HocTap;
    $this->b = HocTap::student('binh');
    $this->eb = $this->h->enroll($this->b);
});

it('HD-1..3 Hỏi kèm giây ở bài video, GV trả lời (thông báo người hỏi + người theo dõi), người hỏi chấp nhận', function () {
    hv($this)->post(route('student.discussions.store', ['course' => $this->h->course, 'lesson' => $this->h->video]), [
        'body' => 'Phút 2 nói gì vậy?', 'second' => 120,
    ])->assertRedirect();
    $d = Discussion::query()->sole();
    expect($d->status)->toBe(DiscussionStatus::ChuaTraLoi)->and($d->video_second)->toBe(120);
    hv($this, $this->b)->post(route('student.discussions.follow', $d))->assertRedirect();
    expect($d->fresh()->follower_count)->toBe(1);

    $this->signIn($this->h->t)->get(route('teacher.discussions.index'))->assertInertia(fn ($p) => $p->where('waiting_count', 1)->has('discussions', 1));
    $this->signIn($this->h->t)->post(route('teacher.discussions.reply', $d), ['body' => 'Giải thích ở đây.'])->assertRedirect();
    $reply = DiscussionReply::query()->sole();
    expect($d->fresh()->status)->toBe(DiscussionStatus::DaTraLoi)
        ->and($this->h->a->notifications()->where('type', NotificationKind::TraLoiHoiDap->value)->count())->toBe(1)
        ->and($this->b->notifications()->where('type', NotificationKind::TraLoiHoiDap->value)->count())->toBe(1);

    // Người theo dõi không phải người hỏi không chấp nhận được.
    hv($this, $this->b)->post(route('student.discussions.accept', ['discussion' => $d, 'reply' => $reply]))->assertForbidden();
    hv($this)->post(route('student.discussions.accept', ['discussion' => $d, 'reply' => $reply]))->assertRedirect();
    expect($d->fresh()->status)->toBe(DiscussionStatus::DaChapNhan)->and($d->fresh()->accepted_reply_id)->toBe($reply->id);
    hv($this)->delete(route('student.discussions.unaccept', $d))->assertRedirect();
    expect($d->fresh()->status)->toBe(DiscussionStatus::DaTraLoi);
});

it('CR-138 Mốc giây chỉ với bài video và trong thời lượng; bài đọc không nhận giây', function () {
    $act = app(Discussions::class);
    expect(fn () => $act->ask($this->h->a, $this->h->enrollment, $this->h->read, 'Hỏi', 5))->toThrow(DomainRejection::class)
        ->and(fn () => $act->ask($this->h->a, $this->h->enrollment, $this->h->video, 'Hỏi', 601))->toThrow(DomainRejection::class);
    expect($act->ask($this->h->a, $this->h->enrollment, $this->h->video, 'Hỏi', 600)->video_second)->toBe(600)
        ->and($act->ask($this->h->a, $this->h->enrollment, $this->h->read, 'Hỏi bài đọc', null)->video_second)->toBeNull();
});

it('CR-78 Ghi danh hết hạn đọc được hỏi đáp nhưng không viết; GV không được giao không trả lời', function () {
    $d = app(Discussions::class)->ask($this->b, $this->eb, $this->h->video, 'Câu hỏi', null);
    $this->h->enrollment->forceFill(['het_han_luc' => now()->subDay()])->save();
    expect(fn () => app(Discussions::class)->ask($this->h->a, $this->h->enrollment->fresh(), $this->h->video, 'Hỏi', null))->toThrow(DomainRejection::class);
    $other = taiKhoan('gv-ngoai-p6@edunest.test', RoleCode::GiaoVien);
    $this->signIn($other)->post(route('teacher.discussions.reply', $d), ['body' => 'Trả lời'])->assertForbidden();
    expect(DiscussionReply::query()->count())->toBe(0);
});

it('HD-4 Giáo vụ ẩn có lý do (học viên không còn thấy), hiện lại về trạng thái trước khi ẩn', function () {
    $d = app(Discussions::class)->ask($this->b, $this->eb, $this->h->video, 'Nội dung không phù hợp', null);
    app(Discussions::class)->reply($this->h->t, $d, 'Đã trả lời');
    $this->signIn($this->h->g)->post(route('academic.discussions.hide', $d), ['reason' => 'Lạc đề'])->assertRedirect();
    expect($d->fresh()->status)->toBe(DiscussionStatus::DaAn)->and($d->fresh()->hidden_reason)->toBe('Lạc đề');
    hv($this)->get($this->h->lessonUrl())->assertInertia(fn ($p) => $p->has('community.discussions', 0));
    $this->signIn($this->h->g)->post(route('academic.discussions.unhide', $d))->assertRedirect();
    expect($d->fresh()->status)->toBe(DiscussionStatus::DaTraLoi);
    $this->signIn($this->h->t)->post(route('academic.discussions.hide', $d), ['reason' => 'x'])->assertForbidden();
});

it('M17 Ghi chú riêng theo giây: chỉ chủ ghi chú sửa/xóa; hiện trong tab Ghi chú của bài', function () {
    hv($this)->post(route('student.notes.store', ['course' => $this->h->course, 'lesson' => $this->h->video]), ['body' => 'Nhớ công thức', 'second' => 42])
        ->assertRedirect();
    $note = LessonNote::query()->sole();
    hv($this)->get($this->h->lessonUrl())->assertInertia(fn ($p) => $p->where('community.notes.0.second', 42)->where('community.can_write', true));
    hv($this, $this->b)->patch(route('student.notes.update', $note), ['body' => 'Sửa trộm'])->assertForbidden();
    hv($this)->patch(route('student.notes.update', $note), ['body' => 'Đã sửa'])->assertRedirect();
    expect($note->fresh()->body)->toBe('Đã sửa');
    app(LessonNotes::class)->delete($this->h->a, $note);
    expect(LessonNote::query()->count())->toBe(0);
});
