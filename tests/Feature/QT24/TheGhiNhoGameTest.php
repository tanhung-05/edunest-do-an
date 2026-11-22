<?php

use App\Actions\M20\PracticeSessions;
use App\Actions\M22\FlashcardAuthoring;
use App\Actions\M22\FlashcardReviews;
use App\Actions\M22\GameSessions;
use App\Enums\M01\RoleCode;
use App\Enums\M20\SessionEndReason;
use App\Enums\M20\SessionStatus;
use App\Enums\M22\CardResult;
use App\Enums\M22\GameKind;
use App\Enums\M22\ReviewGrade;
use App\Models\M21\XpTransaction;
use App\Models\M22\Flashcard;
use App\Models\M22\FlashcardReview;
use App\Models\M22\FlashcardSet;
use App\Models\M22\GameCard;
use App\Models\M22\GameSession;
use App\Support\DomainRejection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;

pest()->group('P7');

function boThe(HocTap $h, int $n = 4): FlashcardSet
{
    $a = app(FlashcardAuthoring::class);
    $set = $a->saveSet($h->t, $h->course, ['title' => 'Từ vựng', 'description' => null, 'course_section_id' => null]);
    foreach (range(1, $n) as $i) {
        $a->saveCard($h->t, $set, ['front' => "word $i", 'back' => "nghĩa $i", 'speech_lang' => 'en-US']);
    }

    return $set;
}

function theGhiNho(FlashcardSet $set, int $i = 1): Flashcard
{
    return Flashcard::query()->where('flashcard_set_id', $set->id)->orderBy('position')->skip($i - 1)->firstOrFail();
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->h = new HocTap;
    $this->r = app(FlashcardReviews::class);
    $this->g = app(GameSessions::class);
});

it('BR-XP-56..61 CR-90 SM-2 rút gọn: lần đầu 1/1/3/4 ngày, lần sau theo hệ số cũ, sàn 1,3', function () {
    expect(FlashcardReviews::schedule(ReviewGrade::Nho, 2.5, null, 0))->toBe(['ease' => 2.5, 'interval' => 3.0, 'days' => 3])
        ->and(FlashcardReviews::schedule(ReviewGrade::De, 2.5, null, 0))->toBe(['ease' => 2.65, 'interval' => 4.0, 'days' => 4])
        ->and(FlashcardReviews::schedule(ReviewGrade::Nho, 2.5, 3.0, 1))->toBe(['ease' => 2.5, 'interval' => 7.5, 'days' => 8])
        ->and(FlashcardReviews::schedule(ReviewGrade::De, 2.5, 7.5, 2))->toBe(['ease' => 2.65, 'interval' => 24.375, 'days' => 25])
        ->and(FlashcardReviews::schedule(ReviewGrade::Kho, 2.0, 10.0, 3))->toBe(['ease' => 1.85, 'interval' => 12.0, 'days' => 12])
        ->and(FlashcardReviews::schedule(ReviewGrade::Quen, 1.4, 30.0, 5))->toBe(['ease' => 1.3, 'interval' => 1.0, 'days' => 1]);
});

it('BR-XP-62 Hàng ôn hôm nay theo giờ VN: thẻ mới vào hàng, chấm xong rời hàng tới ngày ôn', function () {
    $set = boThe($this->h, 2);
    $this->travelTo(CarbonImmutable::parse('2026-11-18 23:30:00', 'Asia/Ho_Chi_Minh'));
    $review = $this->r->grade($this->h->a, theGhiNho($set), ReviewGrade::Nho, 1, 'g1');
    expect($review->due_on->toDateString())->toBe('2026-11-21')->and($review->reps)->toBe(1);
    $this->h->signIn($this)->get(route('student.flashcards.review', $set))->assertInertia(fn ($p) => $p->component('student/flashcards/review')->has('cards', 1));
    $this->travelTo(CarbonImmutable::parse('2026-11-21 00:05:00', 'Asia/Ho_Chi_Minh'));
    $this->h->signIn($this)->get(route('student.flashcards.review', $set))->assertInertia(fn ($p) => $p->has('cards', 2));
    // Gửi lặp cùng mã lượt không ghi thêm.
    $this->r->grade($this->h->a, theGhiNho($set), ReviewGrade::Nho, 1, 'g1');
    expect(FlashcardReview::query()->sole()->reps)->toBe(1);
});

it('A1 A2 BR-XP-63 CR-161 Sửa mặt sau đặt lại lịch, lượt chấm mở trước bị từ chối; sửa mặt trước giữ lịch', function () {
    $set = boThe($this->h, 1);
    $card = theGhiNho($set);
    $this->r->grade($this->h->a, $card, ReviewGrade::De, 1, 'a1');
    $auth = app(FlashcardAuthoring::class);
    $auth->saveCard($this->h->t, $set, ['front' => 'word one', 'back' => 'nghĩa 1', 'speech_lang' => null], card: $card);
    expect(FlashcardReview::query()->sole()->reps)->toBe(1);
    $auth->saveCard($this->h->t, $set, ['front' => 'word one', 'back' => 'nghĩa mới', 'speech_lang' => null], card: $card);
    $review = FlashcardReview::query()->sole();
    expect($review->reps)->toBe(0)->and($review->due_on)->toBeNull()->and($review->ease)->toBe('2.50')
        ->and(fn () => $this->r->grade($this->h->a, $card->fresh(), ReviewGrade::Nho, 1, 'a2'))->toThrow(DomainRejection::class, 'Mở lại thẻ');
    $again = $this->r->grade($this->h->a, $card->fresh(), ReviewGrade::Nho, 2, 'a3');
    expect($again->reps)->toBe(1)->and($again->interval_days)->toBe('3.000');
});

it('BR-XP-64 Ghi danh hết hạn không ôn thẻ, không chơi game; BR-XP-53 GV khóa khác không soạn được bộ thẻ', function () {
    $set = boThe($this->h, 2);
    $this->h->enrollment->forceFill(['het_han_luc' => now()->subMinute()])->save();
    expect(fn () => $this->r->grade($this->h->a, theGhiNho($set), ReviewGrade::Nho, 1, 'x'))->toThrow(DomainRejection::class, 'hết hạn')
        ->and(fn () => $this->g->start($this->h->a, $set, GameKind::MuaChu))->toThrow(DomainRejection::class, 'hết hạn');
    $other = taiKhoan('gv-khac-p7@edunest.test', RoleCode::GiaoVien);
    expect(fn () => app(FlashcardAuthoring::class)->saveCard($other, $set, ['front' => 'a', 'back' => 'b', 'speech_lang' => null]))->toThrow(DomainRejection::class);
    $this->signIn($other)->get(route('teacher.flashcard-sets.show', $set))->assertForbidden();
});

it('BR-XP-67/68 CR-91 Bắn bong bóng: chơi hết lượt → HOAN_THANH, thưởng phiên × hệ số một lần; BR-XP-65 không đổi lịch ôn', function () {
    $set = boThe($this->h, 6);
    $s = $this->g->start($this->h->a, $set, GameKind::BanBong);
    expect($s->card_count)->toBe(6);
    foreach (GameCard::query()->where('game_session_id', $s->id)->orderBy('position')->get() as $i => $card) {
        expect($card->snapshot['options'])->toContain($card->snapshot['back']);
        $s = $this->g->answer($this->h->a, $s->fresh(), $card->position, $i === 0 ? 'sai bét' : $card->snapshot['back'], false, 'c'.$i);
    }
    $this->g->answer($this->h->a, $s->fresh(), 6, 'x', false, 'c5');
    expect($s->status)->toBe(SessionStatus::HoanThanh)
        ->and(XpTransaction::query()->where('rule', 'THUONG_GAME')->sum('amount'))->toEqual(4)
        ->and(FlashcardReview::query()->count())->toBe(0);
    $this->h->signIn($this)->get(route('student.games.result', $s))->assertInertia(fn ($p) => $p->component('student/games/result')->has('again', 1)->has('good', 5)->where('game.xp', 4));
    $this->h->signIn($this)->get(route('student.flashcards.index'))->assertInertia(fn ($p) => $p->has('suggested', 1));
});

it('BR-XP-69 Mưa chữ: chạm đáy hiện đáp án, không phạt; gõ đúng không phân biệt hoa thường', function () {
    $set = boThe($this->h, 2);
    $s = $this->g->start($this->h->a, $set, GameKind::MuaChu);
    $first = GameCard::query()->where('game_session_id', $s->id)->where('position', 1)->firstOrFail();
    $this->g->answer($this->h->a, $s, 1, null, true, 'b1');
    $second = GameCard::query()->where('game_session_id', $s->id)->where('position', 2)->firstOrFail();
    $s = $this->g->answer($this->h->a, $s->fresh(), 2, '  '.mb_strtoupper($second->snapshot['back']).' ', false, 'b2');
    expect($first->fresh()->result)->toBe(CardResult::ChamDay)->and($second->fresh()->result)->toBe(CardResult::Dung)->and($s->status)->toBe(SessionStatus::HoanThanh);
});

it('CR-151 Game mới cùng bộ thẻ chốt phiên cũ BO_DO; im lặng quá 60 phút → BO_DO, 0 XP', function () {
    $set = boThe($this->h, 3);
    $t0 = now()->floorMillisecond();
    $old = $this->g->start($this->h->a, $set, GameKind::MuaChu, $t0);
    $new = $this->g->start($this->h->a, $set, GameKind::BanBong, $t0);
    expect($old->fresh()->status)->toBe(SessionStatus::BoDo)->and($old->fresh()->end_reason)->toBe(SessionEndReason::PhienMoi);
    $this->g->sweep($t0->addMinutes(61));
    expect($new->fresh()->status)->toBe(SessionStatus::BoDo)->and(XpTransaction::query()->count())->toBe(0)
        ->and(PracticeSessions::idleMinutes())->toBe(60);
});

it('MH-GV-16 MH-HV-19 Trang bộ thẻ GV và học viên; bắt đầu game qua HTTP', function () {
    $set = boThe($this->h, 2);
    $this->signIn($this->h->t)->get(route('teacher.flashcard-sets.index'))->assertInertia(fn ($p) => $p->component('teacher/flashcards/index')->has('sets', 1));
    $this->signIn($this->h->t)->get(route('teacher.flashcard-sets.show', $set))->assertInertia(fn ($p) => $p->component('teacher/flashcards/show')->has('cards', 2));
    $this->h->signIn($this)->get(route('student.flashcards.index'))->assertInertia(fn ($p) => $p->component('student/flashcards/index')->where('sets.0.due', 2));
    $this->h->signIn($this)->post(route('student.games.start', $set), ['kind' => 'MUA_CHU'])->assertRedirect();
    $s = GameSession::query()->sole();
    $this->h->signIn($this)->get(route('student.games.play', $s))->assertInertia(fn ($p) => $p->component('student/games/play')->where('card.position', 1)->missing('card.back'));
});
