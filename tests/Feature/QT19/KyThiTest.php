<?php

use App\Actions\M04\ConfidentialBanks;
use App\Actions\M18\ExamClock;
use App\Actions\M18\ExamEvents;
use App\Actions\M18\ExamPapers;
use App\Actions\M18\ExamTaking;
use App\Enums\M01\RoleCode;
use App\Enums\M10\AttemptCloseReason;
use App\Enums\M10\AttemptStatus;
use App\Enums\M15\NotificationKind;
use App\Enums\M18\ExamAudience;
use App\Enums\M18\ExamStatus;
use App\Enums\M18\RegistrationStatus;
use App\Models\M04\Quiz;
use App\Models\M10\QuizAttempt;
use App\Models\M11\AuditLog;
use App\Models\M18\ExamEvent;
use App\Models\M18\ExamRanking;
use App\Models\M18\ExamRegistration;
use App\Models\M18\ExamRegistrationLog;
use App\Models\M18\ExamTabLeave;
use App\Support\DomainRejection;
use App\Support\M10\QuizScore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;
use Tests\Support\KhoaHoc;

pest()->group('P6');

/** Giờ VN ngày 18/11/2026. */
function gio(string $time): CarbonImmutable
{
    return HocTap::at($time);
}

/** Kỳ thi mở 09:00–10:00, 30 phút, đề 4 câu từ ngân hàng bảo mật của GV khóa HocTap. */
function kyThi(object $t, array $over = []): ExamEvent
{
    $t->travelTo(gio('08:00'));
    $event = app(ExamEvents::class)->create($over['organizer'] ?? $t->h->t, [
        'title' => $over['title'] ?? 'Thi thử tháng 11', 'quiz_id' => $t->paper->id,
        'opens_at' => gio($over['opens'] ?? '09:00'), 'closes_at' => gio($over['closes'] ?? '10:00'),
        'duration_minutes' => $over['duration'] ?? 30, 'audience' => $over['audience'] ?? ExamAudience::CongKhai,
        'requires_registration' => $over['requires_registration'] ?? false, 'course_ids' => $over['course_ids'] ?? [], 'publish_at' => null,
    ]);

    return app(ExamEvents::class)->schedule($over['organizer'] ?? $t->h->t, $event);
}

/** Trả lời đúng $n câu đầu của lượt thi. */
function lamDung(QuizAttempt $attempt, int $n, $user): void
{
    $quiz = Quiz::query()->findOrFail($attempt->quiz_id);
    foreach ($quiz->items()->orderBy('position')->get()->take($n) as $q) {
        $right = array_values(array_column(array_filter(QuizScore::options($q), fn ($o) => $o['is_correct']), 'id'));
        app(ExamTaking::class)->save($user, $attempt, $q, $right);
    }
}

beforeEach(function () {
    Storage::fake('local');
    $this->h = new HocTap;
    $this->bank = KhoaHoc::nganHang($this->h->course, $this->h->t, 'Ngân hàng thi', 4);
    app(ConfidentialBanks::class)->set($this->h->t, $this->bank, true);
    $this->paper = app(ExamPapers::class)->create($this->h->t, $this->bank->fresh(), 'Đề thử 1', $this->bank->questions()->pluck('id')->all());
    $this->b = HocTap::student('binh');
    $this->c = HocTap::student('chi');
});

afterEach(fn () => $this->travelBack());

it('BR-ND-63 BR-ND-69 Ngân hàng đang dùng cho quiz bài học không bật bảo mật; đề chỉ lấy từ ngân hàng bảo mật', function () {
    $lessonBank = $this->h->quiz->items()->first()->question->bank;
    expect(fn () => app(ConfidentialBanks::class)->set($this->h->t, $lessonBank, true))->toThrow(DomainRejection::class);
    $open = KhoaHoc::nganHang($this->h->course, $this->h->t, 'Mở', 2);
    expect(fn () => app(ExamPapers::class)->create($this->h->t, $open, 'Đề', $open->questions()->pluck('id')->all()))->toThrow(DomainRejection::class);
    expect($this->paper->kind->value)->toBe('THI_THU')->and($this->paper->items()->count())->toBe(4);
});

it('KT-2 BR-TH-04 BR-ND-70 Chuyển SAP_MO chụp và khóa đề; sửa đề sau đó bị từ chối; tắt bảo mật bị chặn khi kỳ chưa công bố', function () {
    $event = kyThi($this);
    expect($event->status)->toBe(ExamStatus::SapMo)->and($this->paper->fresh()->locked_at)->not->toBeNull()
        ->and($this->paper->items()->whereNull('snapshot')->count())->toBe(0);
    expect(fn () => app(ExamPapers::class)->update($this->h->t, $this->paper, 'Đổi', [$this->bank->questions()->first()->id]))->toThrow(DomainRejection::class);
    expect(fn () => app(ConfidentialBanks::class)->set($this->h->t, $this->bank->fresh(), false))->toThrow(DomainRejection::class);
});

it('CR-141 Giờ mở trong quá khứ hoặc thời lượng vượt cửa sổ bị từ chối khi chuyển SAP_MO', function () {
    $this->travelTo(gio('08:00'));
    $draft = fn (string $opens, string $closes, int $d) => app(ExamEvents::class)->create($this->h->t, [
        'title' => 'X', 'quiz_id' => $this->paper->id, 'opens_at' => gio($opens), 'closes_at' => gio($closes), 'duration_minutes' => $d,
        'audience' => ExamAudience::CongKhai, 'requires_registration' => false, 'course_ids' => [], 'publish_at' => null,
    ]);
    expect(fn () => app(ExamEvents::class)->schedule($this->h->t, $draft('07:00', '10:00', 30)))->toThrow(DomainRejection::class)
        ->and(fn () => app(ExamEvents::class)->schedule($this->h->t, $draft('09:00', '09:20', 30)))->toThrow(DomainRejection::class);
});

it('CR-145 DT-44 Đăng ký, hủy, đăng ký lại dùng một bản ghi; lặp không đổi gì; hủy sau giờ mở bị từ chối', function () {
    $event = kyThi($this, ['requires_registration' => true]);
    $taking = app(ExamTaking::class);
    $taking->register($this->b, $event);
    $taking->register($this->b, $event);
    $taking->cancel($this->b, $event);
    $taking->cancel($this->b, $event);
    $reg = $taking->register($this->b, $event);
    expect(ExamRegistration::query()->count())->toBe(1)->and($reg->status)->toBe(RegistrationStatus::DaDangKy)
        ->and(ExamRegistrationLog::query()->pluck('action')->all())->toBe(['DANG_KY', 'HUY', 'KICH_HOAT_LAI']);
    $this->travelTo(gio('09:00'));
    expect(fn () => $taking->cancel($this->b, $event))->toThrow(DomainRejection::class);
    // Bắt buộc đăng ký: người chưa đăng ký không vào thi.
    app(ExamClock::class)->tick();
    expect(fn () => $taking->start($this->c, $event->fresh()))->toThrow(DomainRejection::class);
});

it('KT-4..6 CR-72/73 Luồng chính: mở, làm, nộp, đóng, công bố; xếp hạng 1,2,2,4; vinh danh chỉ người đồng ý; thông báo kết quả', function () {
    $event = kyThi($this);
    $d = HocTap::student('dung');
    $taking = app(ExamTaking::class);
    $taking->consent($this->h->a, $event, true);
    $this->travelTo(gio('09:00'));
    expect(app(ExamClock::class)->tick()['opened'])->toBe(1);
    // a: 4 đúng trong 10 phút; b, c: 3 đúng cùng thời lượng 20 phút; d: 1 đúng.
    $plan = [[$this->h->a, 4, '09:10'], [$this->b, 3, '09:20'], [$this->c, 3, '09:20'], [$d, 1, '09:25']];
    $attempts = [];
    foreach ($plan as [$u, $n]) {
        $this->travelTo(gio('09:00'));
        $attempts[$u->id] = $taking->start($u, $event->fresh());
        lamDung($attempts[$u->id], $n, $u);
    }
    foreach ($plan as [$u, , $at]) {
        $this->travelTo(gio($at));
        $taking->submit($u, $attempts[$u->id]);
    }
    expect($attempts[$this->h->a->id]->fresh()->status)->toBe(AttemptStatus::DaNop);
    // Trước công bố: trang kết quả không lộ điểm.
    $this->h->signIn($this, $this->b)->get(route('exams.attempts.result', $attempts[$this->b->id]))
        ->assertInertia(fn ($p) => $p->where('published', false)->where('attempt.percent', null));
    $this->travelTo(gio('10:00'));
    $out = app(ExamClock::class)->tick();
    expect($out['closed'])->toBe(1)->and($out['published'])->toBe(1)->and($event->fresh()->status)->toBe(ExamStatus::DaCongBo)
        ->and($this->paper->fresh()->answers_open_at)->not->toBeNull();
    $ranks = ExamRanking::query()->orderBy('rank')->orderBy('id')->get();
    expect($ranks->pluck('rank')->all())->toBe([1, 2, 2, 4])
        ->and($ranks->firstWhere('user_id', $this->h->a->id)->show_name)->toBeTrue()
        ->and($ranks->firstWhere('user_id', $this->b->id)->show_name)->toBeFalse()
        ->and($this->b->notifications()->where('type', NotificationKind::KyThi->value)->count())->toBe(1);
    // Chạy lại không đổi kết quả (idempotent).
    expect(app(ExamClock::class)->tick()['published'])->toBe(0)->and(ExamRanking::query()->count())->toBe(4);
    $this->h->signIn($this, $this->b)->get(route('exams.show', $event))
        ->assertInertia(fn ($p) => $p->where('me.rank', 2)->has('ranking', 4)->where('ranking.0.name', $this->h->a->name)->where('ranking.1.name', $this->b->name)->where('ranking.1.mine', true)->where('ranking.2.name', 'Ẩn danh'));
    $this->get(route('home'))->assertInertia(fn ($p) => $p->where('hall_of_fame.people.0.name', $this->h->a->name)->has('hall_of_fame.people', 1));
});

it('CR-111 CR-142 E34 DT-46 Vào muộn: hạn lượt = giờ đóng; nộp đúng giờ đóng bị từ chối, lượt chốt HET_GIO + DONG_CUA_SO', function () {
    $event = kyThi($this);
    $this->travelTo(gio('09:45'));
    app(ExamClock::class)->tick();
    $attempt = app(ExamTaking::class)->start($this->b, $event->fresh());
    expect($attempt->deadline_at->equalTo(gio('10:00')))->toBeTrue();
    lamDung($attempt, 2, $this->b);
    $this->travelTo(gio('10:00'));
    expect(fn () => app(ExamTaking::class)->submit($this->b, $attempt))->toThrow(DomainRejection::class);
    $attempt->refresh();
    expect($attempt->status)->toBe(AttemptStatus::HetGio)->and($attempt->close_reason)->toBe(AttemptCloseReason::DongCuaSo)
        ->and((float) $attempt->score_earned)->toBe(2.0);
});

it('LT-2 PX Hết hạn lượt trước giờ đóng: HET_GIO lý do HET_GIO; tác vụ nền chốt theo hạn lượt', function () {
    $event = kyThi($this);
    $this->travelTo(gio('09:00'));
    app(ExamClock::class)->tick();
    $attempt = app(ExamTaking::class)->start($this->b, $event->fresh());
    $this->travelTo(gio('09:40'));
    expect(app(ExamClock::class)->tick()['settled'])->toBe(1);
    $attempt->refresh();
    expect($attempt->status)->toBe(AttemptStatus::HetGio)->and($attempt->close_reason)->toBe(AttemptCloseReason::HetGio)
        ->and($attempt->event_at->equalTo(gio('09:30')))->toBeTrue();
});

it('DT-45 Bắt đầu lặp trả cùng một lượt; sau khi nộp không bắt đầu lại', function () {
    $event = kyThi($this);
    $this->travelTo(gio('09:00'));
    app(ExamClock::class)->tick();
    $a1 = app(ExamTaking::class)->start($this->b, $event->fresh());
    $a2 = app(ExamTaking::class)->start($this->b, $event->fresh());
    expect($a1->id)->toBe($a2->id)->and(QuizAttempt::query()->whereNotNull('exam_event_id')->count())->toBe(1);
    app(ExamTaking::class)->submit($this->b, $a1);
    expect(fn () => app(ExamTaking::class)->start($this->b, $event->fresh()))->toThrow(DomainRejection::class);
});

it('CR-74 CR-112 Người soạn đề và nhân sự dự thi được nhưng "Không xếp hạng"', function () {
    $event = kyThi($this);
    $staff = taiKhoan('gv-khac-p6@edunest.test', RoleCode::GiaoVien, RoleCode::HocVien);
    $this->travelTo(gio('09:00'));
    app(ExamClock::class)->tick();
    foreach ([$this->h->t, $staff, $this->b] as $u) {
        $a = app(ExamTaking::class)->start($u, $event->fresh());
        app(ExamTaking::class)->submit($u, $a);
    }
    $this->travelTo(gio('10:00'));
    app(ExamClock::class)->tick();
    expect(ExamRanking::query()->pluck('user_id')->all())->toBe([$this->b->id])
        ->and(ExamTaking::unranked($this->h->t, $event))->toBeTrue()->and(ExamTaking::unranked($staff, $event))->toBeTrue();
});

it('E33 Kỳ dành cho khóa: cần ghi danh còn hạn của khóa chỉ định; thu hồi giữa giờ chốt lượt THU_HOI', function () {
    $event = kyThi($this, ['audience' => ExamAudience::Khoa, 'course_ids' => [$this->h->course->id]]);
    $this->travelTo(gio('09:00'));
    app(ExamClock::class)->tick();
    expect(fn () => app(ExamTaking::class)->start($this->b, $event->fresh()))->toThrow(DomainRejection::class);
    $attempt = app(ExamTaking::class)->start($this->h->a, $event->fresh());
    $this->travelTo(gio('09:05'));
    $this->h->enrollment->forceFill(['revoked_at' => now()])->save();
    app(ExamTaking::class)->onAccessLost($this->h->a, now()->toImmutable());
    expect($attempt->fresh()->close_reason)->toBe(AttemptCloseReason::ThuHoi);
});

it('KT-3 BR-TH-36 Dời lịch trước giờ mở có nhật ký; sau giờ mở bị từ chối; chỉ người tạo kỳ được sửa', function () {
    $event = kyThi($this);
    app(ExamEvents::class)->reschedule($this->h->t, $event, gio('11:00'), gio('12:00'), 45);
    expect($event->fresh()->opens_at->equalTo(gio('11:00')))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'EXAM_RESCHEDULE')->count())->toBe(1);
    expect(fn () => app(ExamEvents::class)->reschedule($this->h->g, $event, gio('11:30'), gio('12:00'), 20))->toThrow(DomainRejection::class);
    $this->travelTo(gio('11:00'));
    expect(fn () => app(ExamEvents::class)->reschedule($this->h->t, $event->fresh(), gio('13:00'), gio('14:00'), 30))->toThrow(DomainRejection::class);
});

it('CR-77/146 Rời tab được ghi; GV người tổ chức thấy số lần, Giáo vụ không có trang tổ chức của kỳ người khác', function () {
    $event = kyThi($this);
    $this->travelTo(gio('09:00'));
    app(ExamClock::class)->tick();
    $attempt = app(ExamTaking::class)->start($this->b, $event->fresh());
    $this->h->signIn($this, $this->b)->postJson(route('exams.attempts.tab-leave', $attempt), ['duration_ms' => 4200])->assertOk();
    expect(ExamTabLeave::query()->count())->toBe(1);
    $this->signIn($this->h->t)->get(route('teacher.exams.show', $event))
        ->assertInertia(fn ($p) => $p->where('participants.0.tab_leaves', 1)->where('participants.0.tab_leave_seconds', 4));
    $this->signIn($this->h->g)->get(route('academic.exams.show', $event))->assertForbidden();
});

it('MH-HV-14 Phòng thi: trang làm bài không lộ đáp án; lưu câu qua JSON; khách bị chuyển đăng nhập', function () {
    $event = kyThi($this);
    $this->get(route('exams.show', $event))->assertRedirect(route('login'));
    $this->travelTo(gio('09:00'));
    app(ExamClock::class)->tick();
    $this->h->signIn($this, $this->b)->post(route('exams.start', $event))->assertRedirect();
    $attempt = QuizAttempt::query()->where('user_id', $this->b->id)->sole();
    $page = $this->h->signIn($this, $this->b)->get(route('exams.attempts.show', $attempt));
    $page->assertInertia(fn ($p) => $p->component('exams/attempt')->has('questions', 4)->missing('questions.0.explanation'));
    $q = $attempt->quiz->items()->first();
    $this->h->signIn($this, $this->b)->patchJson(route('exams.attempts.answers.update', ['attempt' => $attempt, 'quizQuestion' => $q]), ['answer' => [QuizScore::options($q)[0]['id']]])
        ->assertOk()->assertJsonStructure(['received_at']);
    $this->h->signIn($this, $this->c)->patchJson(route('exams.attempts.answers.update', ['attempt' => $attempt, 'quizQuestion' => $q]), ['answer' => null])
        ->assertForbidden();
});
