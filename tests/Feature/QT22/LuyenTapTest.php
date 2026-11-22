<?php

use App\Actions\M01\LockAccount;
use App\Actions\M04\ConfidentialBanks;
use App\Actions\M04\QuestionBankActions;
use App\Actions\M08\RevokeEnrollment;
use App\Actions\M20\ActivityAuthoring;
use App\Actions\M20\PracticeSessions;
use App\Enums\M01\RoleCode;
use App\Enums\M04\QuestionDifficulty;
use App\Enums\M04\QuestionType;
use App\Enums\M20\PracticeMode;
use App\Enums\M20\QuestionResult;
use App\Enums\M20\SessionEndReason;
use App\Enums\M20\SessionStatus;
use App\Models\M04\QuestionBank;
use App\Models\M20\PracticeActivity;
use App\Models\M20\PracticeQuestion;
use App\Models\M20\PracticeSession;
use App\Models\M21\StudentStat;
use App\Models\M21\XpTransaction;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M09\LearningFile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;
use Tests\Support\KhoaHoc;

pest()->group('P7');

function hdLuyenTap(HocTap $h, int $n = 3, string $mode = 'NGAU_NHIEN', ?QuestionBank $bank = null, int $seconds = 30): PracticeActivity
{
    $bank ??= KhoaHoc::nganHang($h->course, $h->t, 'Luyện tập', n: $n);

    return app(ActivityAuthoring::class)->save($h->t, $h->course, ['title' => 'Ôn chương 1', 'question_count' => $n, 'draw_mode' => $mode,
        'seconds_per_question' => $seconds, 'bank_ids' => [$bank->id]]);
}

/** Id lựa chọn đúng/sai của câu đang mở. */
function dapAn(PracticeSession $s, bool $right = true): array
{
    $q = PracticeQuestion::query()->where('activity_session_id', $s->id)->where('position', $s->fresh()->current_position)->firstOrFail();
    $opt = collect($q->snapshot['options'])->first(fn ($o) => $o['is_correct'] === $right);

    return [$q->position, [$opt['id']]];
}

function traLoi(User $u, PracticeSession $s, bool $right = true, ?CarbonImmutable $at = null, ?string $rid = null): PracticeSession
{
    [$pos, $ans] = dapAn($s, $right);

    return app(PracticeSessions::class)->answer($u, $s->fresh(), $pos, $ans, $rid ?? (string) str()->ulid(), $at);
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->h = new HocTap;
    $this->p = app(PracticeSessions::class);
});

it('BR-XP-20..25 CR-84 Learning: đúng lần 1, đúng lần 2, có gợi ý → XP câu + thưởng phiên, HOAN_THANH', function () {
    $bank = KhoaHoc::nganHang($this->h->course, $this->h->t, 'Có gợi ý', n: 0);
    foreach (['DE', 'TRUNG_BINH', 'KHO'] as $i => $d) {
        app(QuestionBankActions::class)->createQuestion($this->h->t, $bank, ['type' => QuestionType::MotDapAn, 'stem' => "Câu $d", 'difficulty' => QuestionDifficulty::from($d),
            'hint' => 'Nhớ lại bài '.$i, 'options' => array_map(fn ($l) => ['content' => $l, 'is_correct' => $l === 'B'], ['A', 'B', 'C'])]);
    }
    $act = hdLuyenTap($this->h, bank: $bank);
    $s = $this->p->start($this->h->a, $act, PracticeMode::Learning);
    expect($s->status)->toBe(SessionStatus::DangLam)->and($s->question_count)->toBe(3)->and($s->level_factor)->toBe('1.00');

    $diff = fn (int $pos) => PracticeQuestion::query()->where('activity_session_id', $s->id)->where('position', $pos)->value('snapshot')['difficulty'];
    $expected = 0;
    // Câu 1 đúng ngay; câu 2 sai rồi đúng; câu 3 dùng gợi ý, sai rồi đúng (CR-84 vẫn dòng gợi ý).
    $expected += ['DE' => 8, 'TRUNG_BINH' => 10, 'KHO' => 12][$diff(1)];
    traLoi($this->h->a, $s);
    traLoi($this->h->a, $s, false);
    expect(PracticeQuestion::query()->where('activity_session_id', $s->id)->where('position', 2)->value('result'))->toBeNull();
    traLoi($this->h->a, $s);
    $expected += ['DE' => 6, 'TRUNG_BINH' => 8, 'KHO' => 10][$diff(2)];
    $this->p->hint($this->h->a, $s->fresh());
    expect(fn () => $this->p->hint($this->h->a, $s->fresh()))->toThrow(DomainRejection::class, 'Mỗi câu chỉ dùng một gợi ý.');
    traLoi($this->h->a, $s, false);
    $s = traLoi($this->h->a, $s);
    $expected += ['DE' => 4, 'TRUNG_BINH' => 5, 'KHO' => 6][$diff(3)];

    expect($s->status)->toBe(SessionStatus::HoanThanh)->and($s->open_key)->toBeNull()
        ->and((int) XpTransaction::query()->where('user_id', $this->h->a->id)->sum('amount'))->toBe($expected + 2)
        ->and(StudentStat::query()->find($this->h->a->id)->correct_answers)->toBe(3);
});

it('BR-XP-08/10 Learning tối đa 2 lần; sai cả hai thì câu SAI, 0 XP; bỏ trống tính sai (A10)', function () {
    $s = $this->p->start($this->h->a, hdLuyenTap($this->h, 2), PracticeMode::Learning);
    traLoi($this->h->a, $s, false);
    traLoi($this->h->a, $s, false);
    $this->p->answer($this->h->a, $s->fresh(), 2, null, 'r-skip-1');
    $s = $this->p->answer($this->h->a, $s->fresh(), 2, null, 'r-skip-2');
    $results = PracticeQuestion::query()->where('activity_session_id', $s->id)->orderBy('position')->pluck('result')->all();
    expect($results)->toBe([QuestionResult::Sai, QuestionResult::BoTrong])->and($s->status)->toBe(SessionStatus::HoanThanh)
        ->and(XpTransaction::query()->where('user_id', $this->h->a->id)->pluck('rule')->map->value->all())->toBe(['THUONG_PHIEN']);
});

it('BR-XP-11..13/15 CR-85 Try Hard: không gợi ý, 3 mạng, mất mạng cuối → HET_MANG chỉ XP câu; hết giờ không mất mạng', function () {
    $act = hdLuyenTap($this->h, 6, seconds: 20);
    $t0 = now()->floorMillisecond();
    $s = $this->p->start($this->h->a, $act, PracticeMode::TryHard, $t0);
    expect($s->lives)->toBe(3)->and(fn () => $this->p->hint($this->h->a, $s, $t0))->toThrow(DomainRejection::class, 'Try Hard không có gợi ý.');
    $s = traLoi($this->h->a, $s, true, $t0->addSeconds(20)); // đúng hạn vẫn nhận
    expect(PracticeQuestion::query()->where('activity_session_id', $s->id)->where('position', 1)->value('result'))->toBe(QuestionResult::Dung);
    // Câu 2 quá hạn: HET_GIO, giữ mạng; trả lời đến sau hạn bị từ chối.
    expect(fn () => $this->p->answer($this->h->a, $s->fresh(), 2, [1], 'late', $t0->addSeconds(41)))->toThrow(DomainRejection::class, 'Câu này đã xử lý xong.');
    $s = $s->fresh();
    expect(PracticeQuestion::query()->where('activity_session_id', $s->id)->where('position', 2)->value('result'))->toBe(QuestionResult::HetGio)
        ->and($s->lives)->toBe(3)->and($s->current_position)->toBe(3);
    $at = $t0->addSeconds(45);
    traLoi($this->h->a, $s, false, $at);
    traLoi($this->h->a, $s, false, $at);
    $s = traLoi($this->h->a, $s, false, $at);
    expect($s->status)->toBe(SessionStatus::HetMang)->and($s->lives)->toBe(0)
        ->and(XpTransaction::query()->where('user_id', $this->h->a->id)->pluck('rule')->map->value->all())->toBe(['CAU']);
});

it('A4 CR-86 Bắt đầu lại cùng hoạt động: phiên cũ BO_DO cùng lần ghi; X4 tập rỗng giữ phiên cũ', function () {
    $bank = KhoaHoc::nganHang($this->h->course, $this->h->t, 'Ít câu', n: 2);
    $act = hdLuyenTap($this->h, 5, bank: $bank);
    $old = $this->p->start($this->h->a, $act, PracticeMode::Learning);
    expect($old->question_count)->toBe(2); // A3: N > số câu → lấy hết
    $new = $this->p->start($this->h->a, $act, PracticeMode::TryHard);
    expect($old->fresh()->status)->toBe(SessionStatus::BoDo)->and($old->fresh()->end_reason)->toBe(SessionEndReason::PhienMoi)
        ->and(PracticeSession::query()->where('status', SessionStatus::DangLam)->count())->toBe(1);
    $bank->questions()->update(['status' => 'NGUNG_DUNG']);
    expect(fn () => $this->p->start($this->h->a, $act, PracticeMode::Learning))->toThrow(DomainRejection::class, 'Hoạt động chưa có câu hỏi khả dụng.');
    expect($new->fresh()->status)->toBe(SessionStatus::DangLam);
});

it('E38 BR-XP-01 Không gắn ngân hàng bảo mật; ngân hàng đang dùng cho luyện tập không bật bảo mật được', function () {
    $secret = KhoaHoc::nganHang($this->h->course, $this->h->t, 'Bảo mật', n: 2);
    app(ConfidentialBanks::class)->set($this->h->t, $secret, true);
    expect(fn () => hdLuyenTap($this->h, bank: $secret->fresh()))->toThrow(DomainRejection::class, 'Không dùng ngân hàng bảo mật cho luyện tập (E38).');
    $plain = KhoaHoc::nganHang($this->h->course, $this->h->t, 'Thường', n: 2);
    hdLuyenTap($this->h, bank: $plain);
    expect(fn () => app(ConfidentialBanks::class)->set($this->h->t, $plain, true))->toThrow(DomainRejection::class, 'Ngân hàng đang dùng cho hoạt động luyện tập');
});

it('X1 CR-151 Quá 60 phút không hoạt động → BO_DO, 0 XP; đúng ngưỡng vẫn nhận; TV-14 chốt khi học viên im lặng', function () {
    $act = hdLuyenTap($this->h);
    $t0 = now()->floorMillisecond();
    $s = $this->p->start($this->h->a, $act, PracticeMode::Learning, $t0);
    $s = traLoi($this->h->a, $s, true, $t0->addMinutes(60));
    expect($s->status)->toBe(SessionStatus::DangLam);
    expect(fn () => traLoi($this->h->a, $s, true, $t0->addMinutes(120)->addMillisecond()))->toThrow(DomainRejection::class, 'không hoạt động');
    expect($s->fresh()->status)->toBe(SessionStatus::BoDo)->and($s->fresh()->end_reason)->toBe(SessionEndReason::KhongHoatDong)
        ->and(XpTransaction::query()->count())->toBe(0);

    $s2 = $this->p->start($this->h->a, $act, PracticeMode::Learning, $t0);
    $this->p->sweep($t0->addMinutes(61));
    expect($s2->fresh()->status)->toBe(SessionStatus::BoDo);
});

it('E22 X5 CR-155 Thu hồi quyền học và khóa tài khoản chốt phiên đang làm BO_DO', function () {
    $act = hdLuyenTap($this->h);
    $s = $this->p->start($this->h->a, $act, PracticeMode::Learning);
    app(RevokeEnrollment::class)->revoke($this->h->g, $this->h->enrollment, 'Vi phạm', true);
    expect($s->fresh()->status)->toBe(SessionStatus::BoDo)->and($s->fresh()->end_reason)->toBe(SessionEndReason::ThuHoi)
        ->and(fn () => $this->p->start($this->h->a, $act, PracticeMode::Learning))->toThrow(DomainRejection::class, 'thu hồi');

    $b = HocTap::student('binh');
    $this->h->enroll($b);
    $s2 = $this->p->start($b, $act, PracticeMode::TryHard);
    $admin = taiKhoan('qt-p7@edunest.test', RoleCode::QuanTri);
    app(LockAccount::class)->handle($admin, $b, 'Khóa thử');
    expect($s2->fresh()->status)->toBe(SessionStatus::BoDo)->and($s2->fresh()->end_reason)->toBe(SessionEndReason::KhoaTaiKhoan);
});

it('E16 BR-XP-18 Ghi danh hết hạn: chặn mở phiên mới, phiên đã bắt đầu được làm nốt và nhận XP', function () {
    $act = hdLuyenTap($this->h, 1);
    $s = $this->p->start($this->h->a, $act, PracticeMode::Learning);
    $this->h->enrollment->forceFill(['het_han_luc' => now()->subMinute()])->save();
    expect(fn () => $this->p->start($this->h->a, $act, PracticeMode::Learning))->toThrow(DomainRejection::class, 'hết hạn');
    $s = traLoi($this->h->a, $s);
    expect($s->status)->toBe(SessionStatus::HoanThanh)->and(XpTransaction::query()->sum('amount'))->toBeGreaterThan(0);
});

it('X3 E40 BR-XP-52 Gửi lặp cùng mã trả lời giữ kết quả; trả lời vào phiên đã chốt bị từ chối; chỉ thưởng một lần', function () {
    $s = $this->p->start($this->h->a, hdLuyenTap($this->h, 1), PracticeMode::Learning);
    [$pos, $ans] = dapAn($s);
    $this->p->answer($this->h->a, $s, $pos, $ans, 'same');
    $again = $this->p->answer($this->h->a, $s->fresh(), $pos, $ans, 'same');
    expect($again->status)->toBe(SessionStatus::HoanThanh)
        ->and(fn () => $this->p->answer($this->h->a, $s->fresh(), $pos, $ans, 'other'))->toThrow(DomainRejection::class, 'Phiên đã kết thúc.')
        ->and(XpTransaction::query()->where('rule', 'THUONG_PHIEN')->count())->toBe(1);
});

it('CR-153 BR-XP-04 Ưu tiên điểm yếu: câu sai ≥ 2 lần trong 10 phiên gần nhất được rút trước', function () {
    $bank = KhoaHoc::nganHang($this->h->course, $this->h->t, 'Điểm yếu', n: 6);
    $weak = hdLuyenTap($this->h, 1, 'DIEM_YEU', $bank);
    $all = hdLuyenTap($this->h, 6, bank: $bank);
    // Hai phiên Try Hard sai cùng một câu (câu đầu của phiên rút ngẫu nhiên đầy đủ → chọn câu cụ thể).
    $target = $bank->questions()->orderBy('id')->value('id');
    foreach ([1, 2] as $_) {
        $s = $this->p->start($this->h->a, $all, PracticeMode::TryHard);
        foreach (range(1, 6) as $_i) {
            $q = PracticeQuestion::query()->where('activity_session_id', $s->id)->where('position', $s->fresh()->current_position)->first();
            if ($s->fresh()->status !== SessionStatus::DangLam) {
                break;
            }
            $s = traLoi($this->h->a, $s, $q->question_id !== $target);
        }
    }
    $s = $this->p->start($this->h->a, $weak, PracticeMode::Learning);
    expect(PracticeQuestion::query()->where('activity_session_id', $s->id)->value('question_id'))->toBe($target);
});

it('MH-HV-16/17 Trang luyện tập: bắt đầu, làm câu, kết quả; học viên khác 403', function () {
    $act = hdLuyenTap($this->h, 1);
    $this->h->signIn($this)->get(route('student.practice.index'))->assertInertia(fn ($p) => $p->component('student/practice/index')->has('courses.0.activities', 1));
    $this->h->signIn($this)->post(route('student.practice.start', $act), ['mode' => 'LEARNING'])->assertRedirect();
    $s = PracticeSession::query()->sole();
    $this->h->signIn($this)->get(route('student.practice.sessions.show', $s))
        ->assertInertia(fn ($p) => $p->component('student/practice/session')->where('question.position', 1)->missing('question.choices.0.is_correct'));
    [$pos, $ans] = dapAn($s);
    $this->h->signIn($this)->post(route('student.practice.sessions.answer', $s), ['position' => $pos, 'answer' => $ans, 'request_id' => 'abc'])
        ->assertRedirect(route('student.practice.sessions.result', $s));
    $this->h->signIn($this)->get(route('student.practice.sessions.result', $s))->assertInertia(fn ($p) => $p->where('session.status', 'HOAN_THANH')->where('session.xp', 12));

    session()->forget(LearningFile::DEVICE);
    session()->save();
    $other = HocTap::student('khac');
    $this->h->signIn($this, $other)->get(route('student.practice.sessions.show', $s))->assertForbidden();
});
