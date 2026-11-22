<?php

use App\Actions\M04\ConfidentialBanks;
use App\Actions\M18\ExamClock;
use App\Actions\M18\ExamEvents;
use App\Actions\M18\ExamPapers;
use App\Actions\M18\ExamTaking;
use App\Actions\M23\GuardianLinks;
use App\Actions\M23\GuardianReports;
use App\Enums\M01\RoleCode;
use App\Enums\M18\ExamAudience;
use App\Models\M18\ExamRanking;
use App\Models\M23\GuardianInvite;
use App\Models\M23\GuardianLink;
use App\Models\User;
use App\Notifications\GuardianWeeklyMail;
use App\Support\DomainRejection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;
use Tests\Support\KhoaHoc;

pest()->group('P8');

function phGio(string $at): CarbonImmutable
{
    return CarbonImmutable::parse($at, 'Asia/Ho_Chi_Minh')->utc();
}

function phHuynh(string $name): User
{
    return taiKhoan($name.'-ph@edunest.test', RoleCode::PhuHuynh);
}

function phMa(User $student, string $at = '2026-12-01 09:00:00'): GuardianInvite
{
    return app(GuardianLinks::class)->issue($student, phGio($at));
}

function phBiTuChoi(callable $fn): string
{
    try {
        $fn();
    } catch (DomainRejection $e) {
        return $e->status === 403 ? 'forbidden' : 'rejected';
    }

    return 'ok';
}

beforeEach(function () {
    Storage::fake('local');
    $this->h = new HocTap;
    $this->a = $this->h->a;
    $this->b = HocTap::student('binh');
    $this->p = phHuynh('p');
    $this->q = phHuynh('q');
    $this->links = app(GuardianLinks::class);
});

it('AC-25-01 AC-25-36 BR-MR-02 Khách đăng ký Phụ huynh bằng mã: một tài khoản, một liên kết, mã đã dùng; mã sai thì không có tài khoản', function () {
    $m1 = phMa($this->a);
    $this->post(route('register.guardian.store'), ['name' => 'Phụ huynh Mới', 'email' => 'moi-ph@edunest.test',
        'password' => 'Matkhau!2345', 'password_confirmation' => 'Matkhau!2345', 'code' => 'SAIMA999'])->assertSessionHasErrors('code');
    expect(User::query()->where('email', 'moi-ph@edunest.test')->exists())->toBeFalse()->and($m1->fresh()->used_at)->toBeNull();

    $this->post(route('register.guardian.store'), ['name' => 'Phụ huynh Mới', 'email' => 'moi-ph@edunest.test',
        'password' => 'Matkhau!2345', 'password_confirmation' => 'Matkhau!2345', 'code' => strtolower($m1->code)])->assertRedirect(route('guardian.dashboard'));
    $user = User::query()->where('email', 'moi-ph@edunest.test')->firstOrFail();
    expect($user->hasEffectiveRole(RoleCode::PhuHuynh))->toBeTrue()->and($user->hasEffectiveRole(RoleCode::HocVien))->toBeFalse()
        ->and(GuardianLink::query()->where('guardian_id', $user->id)->where('student_id', $this->a->id)->count())->toBe(1)
        ->and($m1->fresh()->used_by_id)->toBe($user->id);
});

it('AC-25-02 AC-25-03 BR-MR-03 Nhiều–nhiều; mỗi cặp một liên kết; mã đã dùng hoặc sai bị từ chối', function () {
    $this->links->link($this->p, phMa($this->a)->code, phGio('2026-12-01 09:05'));
    $this->links->link($this->p, phMa($this->b)->code, phGio('2026-12-01 09:05'));
    $m3 = phMa($this->a);
    $this->links->link($this->q, $m3->code, phGio('2026-12-01 09:06'));
    expect(GuardianLink::query()->where('guardian_id', $this->p->id)->count())->toBe(2)
        ->and(GuardianLink::query()->where('student_id', $this->a->id)->count())->toBe(2);

    $m4 = phMa($this->a);
    expect(phBiTuChoi(fn () => $this->links->link($this->q, $m3->code, phGio('2026-12-01 09:07'))))->toBe('rejected')
        ->and(phBiTuChoi(fn () => $this->links->link($this->q, 'KHONGCO9', phGio('2026-12-01 09:07'))))->toBe('rejected')
        ->and(phBiTuChoi(fn () => $this->links->link($this->p, $m4->code, phGio('2026-12-01 09:07'))))->toBe('rejected')
        ->and($m4->fresh()->used_at)->toBeNull()
        ->and(GuardianLink::query()->where('guardian_id', $this->p->id)->where('student_id', $this->a->id)->count())->toBe(1);
});

it('AC-25-04 BR-MR-01 Hạn 7 ngày tính tới đúng mốc cấp: 09:00:00 ngày 08/12 còn dùng, 09:00:01 hết hạn và mã chưa tiêu', function () {
    $m1 = phMa($this->a);
    expect(phBiTuChoi(fn () => $this->links->link($this->p, $m1->code, phGio('2026-12-08 09:00:01'))))->toBe('rejected')
        ->and($m1->fresh()->used_at)->toBeNull();
    $this->links->link($this->p, $m1->code, phGio('2026-12-08 09:00:00'));
    expect($m1->fresh()->used_at)->not->toBeNull();
});

it('AC-25-05 AC-25-06 AC-25-07 Tự liên kết, sai vai, gỡ cặp không phải của mình đều bị từ chối', function () {
    $m1 = phMa($this->a);
    $both = taiKhoan('ca-hai@edunest.test', RoleCode::HocVien, RoleCode::PhuHuynh);
    $own = phMa($both);
    expect(phBiTuChoi(fn () => $this->links->link($both, $own->code)))->toBe('rejected')
        ->and(phBiTuChoi(fn () => $this->links->issue($this->p)))->toBe('forbidden')
        ->and(phBiTuChoi(fn () => $this->links->link($this->a, $m1->code)))->toBe('forbidden');
    $link = $this->links->link($this->p, $m1->code, phGio('2026-12-01 09:05'));
    expect(phBiTuChoi(fn () => $this->links->unlink($this->h->g, $link->id)))->toBe('forbidden')
        ->and(phBiTuChoi(fn () => $this->links->unlink($this->b, $link->id)))->toBe('forbidden')
        ->and(phBiTuChoi(fn () => $this->links->unlink($this->q, $link->id)))->toBe('forbidden')
        ->and($link->fresh()->removed_at)->toBeNull()
        ->and(GuardianInvite::query()->where('student_id', $this->p->id)->exists())->toBeFalse();
});

it('AC-25-08..10 AC-25-40 BR-MR-04/05 Phụ huynh đọc năm nhóm dữ liệu của con đã liên kết, không có đáp án; con khác và vai khác bị 403', function () {
    $this->links->link($this->p, phMa($this->a)->code);
    $this->h->mark($this->h->read);
    $this->h->submit(3);

    $res = $this->signIn($this->p)->get(route('guardian.children.show', $this->a->id))->assertOk();
    $report = $res->viewData('page')['props']['report'];
    expect($report['student']['id'])->toBe($this->a->id)->and($report['courses'])->toHaveCount(1)
        ->and($report['courses'][0]['completed'])->toBeGreaterThanOrEqual(1)
        ->and(collect($report['courses'][0]['quizzes'])->pluck('best_percent')->filter()->first())->toBe('100.0')
        ->and(json_encode($report))->not->toContain('is_correct')->not->toContain('explanation');

    $this->signIn($this->p)->get(route('guardian.children.show', $this->b->id))->assertForbidden();
    $this->signIn($this->h->g)->get(route('guardian.children.show', $this->a->id))->assertForbidden();
    $this->signIn($this->p)->get(route('student.certificates.index'))->assertForbidden();
});

it('AC-25-11 AC-25-34 BR-MR-07 Gỡ liên kết chặn đọc ngay; mã mới liên kết lại cùng dòng của cặp', function () {
    $link = $this->links->link($this->p, phMa($this->a)->code, phGio('2026-12-01 09:05'));
    $this->signIn($this->a)->delete(route('student.guardians.links.destroy', $link->id))->assertRedirect();
    $this->signIn($this->p)->get(route('guardian.children.show', $this->a->id))->assertForbidden();

    $again = $this->links->link($this->p, phMa($this->a, '2026-12-02 09:00')->code, phGio('2026-12-02 09:25'));
    expect($again->id)->toBe($link->id)->and($again->removed_at)->toBeNull()
        ->and(GuardianLink::query()->where('guardian_id', $this->p->id)->count())->toBe(1);
    $this->signIn($this->p)->get(route('guardian.children.show', $this->a->id))->assertOk();
});

it('AC-25-12 AC-25-35 AC-25-41 BR-MR-08 CR-234 Báo cáo tuần: chỉ cặp còn hiệu lực, mỗi (cặp, tuần) một lần, không gửi khi đã gỡ lúc gửi', function () {
    Notification::fake();
    $this->links->link($this->p, phMa($this->a)->code);
    $pb = $this->links->link($this->p, phMa($this->b)->code);
    $this->links->link($this->q, phMa($this->b)->code);
    $this->links->unlink($this->p, $pb->id);

    $sunday = phGio('2026-12-06 08:00');
    expect(app(GuardianReports::class)->sendWeek($sunday))->toBe(2)
        ->and(app(GuardianReports::class)->sendWeek($sunday->addHour()))->toBe(0)
        ->and(DB::table('guardian_weekly_reports')->where('week_start', '2026-11-30')->count())->toBe(2);
    Notification::assertSentTo($this->p, GuardianWeeklyMail::class, fn (GuardianWeeklyMail $m) => $m->studentId === $this->a->id
        && ! str_contains(implode("\n", $m->lines), 'đáp án'));
    Notification::assertNotSentTo($this->p, GuardianWeeklyMail::class, fn (GuardianWeeklyMail $m) => $m->studentId === $this->b->id);

    // Gỡ sau khi đã xếp email nhưng trước lúc gửi: kiểm lại liên kết khi gửi (BR-HT-60).
    $mail = new GuardianWeeklyMail($this->b->id, 'x', [], 'http://localhost');
    $qb = GuardianLink::query()->where('guardian_id', $this->q->id)->firstOrFail();
    expect($mail->shouldSend($this->q, 'mail'))->toBeTrue();
    $this->links->unlink($this->b, $qb->id);
    expect($mail->shouldSend($this->q, 'mail'))->toBeFalse();
});

it('AC-25-13 AC-25-23 AC-25-24 CR-98 CR-112 Phụ huynh tự thi kỳ công khai: "Không xếp hạng", không chiếm hạng; không đồng ý công khai tên thay con', function () {
    $bank = KhoaHoc::nganHang($this->h->course, $this->h->t, 'Ngân hàng thi PH', 2);
    app(ConfidentialBanks::class)->set($this->h->t, $bank, true);
    $paper = app(ExamPapers::class)->create($this->h->t, $bank->fresh(), 'Đề PH', $bank->questions()->pluck('id')->all());
    $this->travelTo(HocTap::at('08:00'));
    $event = app(ExamEvents::class)->create($this->h->t, ['title' => 'Thi công khai', 'quiz_id' => $paper->id,
        'opens_at' => HocTap::at('09:00'), 'closes_at' => HocTap::at('10:00'), 'duration_minutes' => 30,
        'audience' => ExamAudience::CongKhai, 'requires_registration' => false, 'course_ids' => [], 'publish_at' => null]);
    $event = app(ExamEvents::class)->schedule($this->h->t, $event);
    $this->travelTo(HocTap::at('09:00'));
    app(ExamClock::class)->tick();
    foreach ([$this->p, $this->a] as $u) {
        $attempt = app(ExamTaking::class)->start($u, $event->fresh());
        app(ExamTaking::class)->submit($u, $attempt);
    }
    $this->travelTo(HocTap::at('10:00'));
    app(ExamClock::class)->tick();
    expect(ExamTaking::unranked($this->p, $event->fresh()))->toBeTrue()
        ->and(ExamRanking::query()->where('exam_event_id', $event->id)->pluck('user_id')->all())->toBe([$this->a->id]);
    $this->travelBack();
});

it('MH-PH-01 MH-HV-21 Trang Phụ huynh và trang mã mời của học viên; khách mở trang đăng ký Phụ huynh', function () {
    $this->get(route('register.guardian.create', ['code' => 'ABC']))->assertOk();
    $this->signIn($this->a)->post(route('student.guardians.invites.store'))->assertRedirect();
    $code = GuardianInvite::query()->where('student_id', $this->a->id)->value('code');
    $this->signIn($this->a)->get(route('student.guardians.index'))->assertOk();
    $this->signIn($this->p)->post(route('guardian.links.store'), ['code' => $code])->assertRedirect();
    $this->signIn($this->p)->get(route('guardian.dashboard'))->assertOk();
    $this->signIn($this->p)->get(route('dashboard'))->assertRedirect(route('guardian.dashboard'));
    $this->signIn($this->a)->get(route('guardian.dashboard'))->assertForbidden();
});
