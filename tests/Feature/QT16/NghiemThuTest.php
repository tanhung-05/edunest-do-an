<?php

use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M08\RevokeEnrollment;
use App\Actions\M14\CourseRatings;
use App\Enums\M01\RoleCode;
use App\Enums\M14\RatingStatus;
use App\Models\M14\CourseRating;
use App\Support\DomainRejection;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;

pest()->group('P5');

beforeEach(function () {
    $this->travelTo(HocTap::at('09:00:00'));
    Storage::fake('local');
    $this->h = new HocTap;
    $this->h->enrollment->update(['het_han_luc' => now()->addMonth()]);
    app(ChangeSaleStatus::class)->start($this->h->g, $this->h->course->fresh());
});

afterEach(fn () => $this->travelBack());

function danhGiaP5(object $t, int $stars, ?string $comment = null): CourseRating
{
    return app(CourseRatings::class)->submit($t->h->a, $t->h->course, $stars, $comment);
}

it('AC-16-01 AC-16-02 Đủ 30% bài bắt buộc mới đánh giá; điểm trung bình cập nhật', function () {
    expect(fn () => danhGiaP5($this, 4))->toThrow(DomainRejection::class, '30%');
    $this->h->mark($this->h->read);
    $r = danhGiaP5($this, 4, 'Dễ hiểu');
    expect($r->status)->toBe(RatingStatus::Hien)->and($this->h->course->fresh()->rating_count)->toBe(1)
        ->and($this->h->course->fresh()->rating_avg)->toBe('4.00');
    $this->get(route('public.courses.show', $this->h->course->slug))->assertOk()
        ->assertInertia(fn ($p) => $p->where('ratings.count', 1)->where('ratings.distribution', [0, 1, 0, 0, 0]));
});

it('AC-16-06 AC-16-07 Gửi lần nữa là sửa; biên số sao', function () {
    $this->h->mark($this->h->read);
    danhGiaP5($this, 4, 'Dễ hiểu');
    danhGiaP5($this, 5, 'Rất tốt');
    expect(CourseRating::query()->count())->toBe(1)->and(CourseRating::query()->sole()->stars)->toBe(5);
    foreach ([0, 6] as $bad) {
        expect(fn () => danhGiaP5($this, $bad))->toThrow(DomainRejection::class);
    }
    $this->signIn($this->h->a)->post(route('student.ratings.store', $this->h->course), ['stars' => 4.5])->assertSessionHasErrors('stars');
});

it('AC-16-04 AC-16-05 AC-15-44 Hết hạn hoặc bị thu hồi thì chặn cả viết mới và sửa', function () {
    $this->h->mark($this->h->read);
    danhGiaP5($this, 4);
    $this->h->enrollment->update(['het_han_luc' => now()->subMinute()]);
    expect(fn () => danhGiaP5($this, 5))->toThrow(DomainRejection::class, 'hết hạn');
    $this->h->enrollment->update(['het_han_luc' => now()->addMonth()]);
    app(RevokeEnrollment::class)->revoke($this->h->g, $this->h->enrollment, 'Vi phạm', true);
    expect(fn () => danhGiaP5($this, 5))->toThrow(DomainRejection::class, 'thu hồi');
    expect(CourseRating::query()->sole()->stars)->toBe(4);
});

it('AC-16-08 AC-16-09 Sai vai và chưa ghi danh không đánh giá được', function () {
    $b = HocTap::student('binh');
    $this->signIn($b)->post(route('student.ratings.store', $this->h->course), ['stars' => 5])->assertForbidden();
    $this->signIn($this->h->g)->post(route('student.ratings.store', $this->h->course), ['stars' => 5])->assertForbidden();
    expect(CourseRating::query()->count())->toBe(0);
});

it('AC-16-10 AC-16-11 AC-16-12 Giáo viên được giao trả lời; người khác 403', function () {
    $this->h->mark($this->h->read);
    $r = danhGiaP5($this, 4);
    $this->signIn($this->h->t)->post(route('teacher.reviews.reply', $r), ['reply' => 'Cảm ơn bạn'])->assertRedirect();
    expect($r->fresh()->reply)->toBe('Cảm ơn bạn');
    $g2 = taiKhoan('g2-p5@edunest.test', RoleCode::GiaoVien);
    $this->signIn($g2)->post(route('teacher.reviews.reply', $r), ['reply' => 'x'])->assertForbidden();
    $this->signIn($this->h->a)->post(route('teacher.reviews.reply', $r), ['reply' => 'x'])->assertForbidden();
});

it('AC-16-13 AC-16-16 AC-16-22 AC-16-23 Ẩn: người viết thấy lý do, người khác không thấy, câu trả lời ẩn theo; sửa bị chặn; hiện lại', function () {
    $this->h->mark($this->h->read);
    $r = danhGiaP5($this, 2, 'Tệ');
    app(CourseRatings::class)->reply($this->h->t, $r, 'Cảm ơn góp ý');
    app(CourseRatings::class)->hide($this->h->g, $r, 'Ngôn từ xúc phạm');
    expect($this->h->course->fresh()->rating_count)->toBe(0)->and($this->h->course->fresh()->rating_avg)->toBeNull();
    $this->get(route('public.courses.show', $this->h->course->slug))
        ->assertInertia(fn ($p) => $p->where('ratings.items', [])->where('ratings.own', null));
    $this->signIn($this->h->a)->get(route('public.courses.show', $this->h->course->slug))
        ->assertInertia(fn ($p) => $p->where('ratings.own.hidden', true)->where('ratings.own.hidden_reason', 'Ngôn từ xúc phạm')->where('ratings.own.reply', null));
    expect(fn () => danhGiaP5($this, 5))->toThrow(DomainRejection::class, 'đang bị ẩn');
    expect(fn () => app(CourseRatings::class)->hide($this->h->g, $r, ''))->toThrow(DomainRejection::class);
    app(CourseRatings::class)->unhide($this->h->g, $r, 'Đã xem lại');
    expect($r->fresh()->status)->toBe(RatingStatus::Hien)->and($this->h->course->fresh()->rating_count)->toBe(1);
    dangXuat();
    $this->get(route('public.courses.show', $this->h->course->slug))->assertInertia(fn ($p) => $p->where('ratings.items.0.reply', 'Cảm ơn góp ý'));
});

it('AC-16-14 AC-16-17 Sai vai không ẩn hoặc hiện lại', function () {
    $this->h->mark($this->h->read);
    $r = danhGiaP5($this, 3);
    $this->signIn($this->h->t)->post(route('academic.moderation.ratings.hide', $r), ['reason' => 'x'])->assertForbidden();
    $this->signIn($this->h->a)->post(route('academic.moderation.ratings.hide', $r), ['reason' => 'x'])->assertForbidden();
    expect($r->fresh()->status)->toBe(RatingStatus::Hien);
});

it('AC-16-18 Điểm trung bình chỉ tính đánh giá hiển thị', function () {
    $course = $this->h->course;
    foreach ([5, 4, 4, 3] as $i => $s) {
        $u = HocTap::student('sv'.$i);
        $e = $this->h->enroll($u);
        $e->update(['het_han_luc' => now()->addMonth()]);
        $this->h->mark($this->h->read, $e);
        app(CourseRatings::class)->submit($u, $course, $s, null);
    }
    expect($course->fresh()->rating_avg)->toBe('4.00')->and($course->fresh()->rating_count)->toBe(4);
});
