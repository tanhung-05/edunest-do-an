<?php

use App\Actions\M10\SubmitQuizAttempt;
use App\Enums\M01\RoleCode;
use App\Enums\M07\PaymentReconcileStatus;
use App\Enums\M07\PaymentTxnStatus;
use App\Models\M07\Payment;
use App\Models\M10\QuizAttempt;
use App\Models\M19\CoursePageView;
use App\Queries\M19\AcademicDashboard;
use App\Queries\M19\QuizAnalytics;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;
use Tests\Support\MuaHang as MH;
use Tests\Support\ThuongMai as TM;

pest()->group('P6');

afterEach(fn () => $this->travelBack());

it('CR-113 Lượt xem trang khóa: một lượt cho mỗi phiên–ngày–khóa', function () {
    $d = TM::d13();
    $url = route('public.courses.show', $d['k1']->slug);
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->get($url)->assertOk();
    $this->get($url)->assertOk();
    expect(CoursePageView::query()->count())->toBe(1);
    $this->travel(1)->days();
    $this->get($url)->assertOk();
    expect(CoursePageView::query()->count())->toBe(2);
});

it('BR-BC-04 CR-148 Dashboard: doanh thu chỉ khoản HOP_LE theo ngày VN; phễu xem→mua→bắt đầu→hoàn thành; 0 lượt xem thì "chưa đủ dữ liệu"', function () {
    $d = TM::d13();
    $empty = AcademicDashboard::build(...AcademicDashboard::range('2026-11-20', '2026-11-20'));
    expect($empty['funnel']['conversion'])->toBeNull()->and($empty['revenue']['total'])->toBe(0);

    $this->get(route('public.courses.show', $d['k1']->slug));
    $order = MH::dat($d['a'], $d['k1']);
    MH::xacNhan(MH::khoiTao($d['a'], $order));
    MH::chayCap();
    $paid = Payment::query()->sole();
    expect($paid->txn_status)->toBe(PaymentTxnStatus::ThanhCong)->and($paid->reconcile_status)->toBe(PaymentReconcileStatus::HopLe);
    $stats = AcademicDashboard::build(...AcademicDashboard::range('2026-11-20', '2026-11-20'));
    expect($stats['revenue']['total'])->toBe((int) $paid->received_amount)
        ->and($stats['funnel']['views'])->toBe(1)->and($stats['funnel']['orders'])->toBe(1)->and($stats['funnel']['conversion'])->toBe('100.0')
        ->and($stats['top_courses'][0]['course_id'])->toBe($d['k1']->id);
    // Khoản CAN_HOAN không tính.
    $paid->forceFill(['reconcile_status' => PaymentReconcileStatus::CanHoan])->save();
    expect(AcademicDashboard::build(...AcademicDashboard::range('2026-11-20', '2026-11-20'))['revenue']['total'])->toBe(0);
});

it('BR-BC-03 Dashboard Giáo vụ có lọc ngày và xuất Excel; Giáo viên không vào được', function () {
    $d = TM::d13();
    $this->signIn($d['lan'])->get(route('academic.dashboard', ['from' => '2026-11-01', 'to' => '2026-11-30']))
        ->assertInertia(fn ($p) => $p->where('stats.from', '2026-11-01')->where('stats.to', '2026-11-30')->has('stats.funnel'));
    $res = $this->signIn($d['lan'])->get(route('academic.dashboard.export', ['from' => '2026-11-01', 'to' => '2026-11-30']));
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('spreadsheetml');
    $teacher = taiKhoan('gv-p6-dash@edunest.test', RoleCode::GiaoVien);
    $this->signIn($teacher)->get(route('academic.dashboard'))->assertForbidden();
});

it('AC-20 Khoảng ngày sai hoặc quá 366 ngày được chuẩn hóa', function () {
    [$s, $e] = AcademicDashboard::range('2026-13-40', 'abc');
    expect($s->diffInDays($e))->toBe(29.0);
    [$s, $e] = AcademicDashboard::range('2024-01-01', '2026-11-30');
    expect((int) $s->diffInDays($e))->toBe(365);
});

it('BR-BC-09/10 CR-149 Phân tích quiz: tiến độ, trung bình/trung vị theo điểm ghi nhận, phân bố, từng câu; GV khác bị chặn; xuất Excel', function () {
    Storage::fake('local');
    $h = new HocTap(questionCount: 4);
    $b = HocTap::student('binh');
    $eb = $h->enroll($b);
    $c = HocTap::student('chi');
    $h->enroll($c);
    foreach ([$h->read, $h->video] as $l) {
        $h->mark($l);
        $h->mark($l, $eb);
    }
    $h->submit(4);
    $att = $h->start($b, $eb);
    $h->fill($att, 2, $b);
    app(SubmitQuizAttempt::class)->handle($b, $att);

    $data = QuizAnalytics::build($h->quiz, QuizAttempt::query()->where('quiz_id', $h->quiz->id), 3);
    expect($data['progress'])->toMatchArray(['assigned' => 3, 'not_started' => 1, 'submitted' => 2])
        ->and($data['scores']['average'])->toBe('75.0')->and($data['scores']['median'])->toBe('75.0')
        ->and($data['scores']['max'])->toBe('100.0')->and($data['scores']['min'])->toBe('50.0')
        ->and($data['buckets'][9]['count'])->toBe(1)->and($data['buckets'][5]['count'])->toBe(1)
        ->and($data['questions'][0]['correct'])->toBe(2)->and($data['questions'][3]['correct'])->toBe(1)->and($data['questions'][3]['wrong'] + $data['questions'][3]['blank'])->toBe(1);

    $this->signIn($h->t)->get(route('teacher.courses.quiz-report', $h->course))->assertInertia(fn ($p) => $p->where('analytics.progress.assigned', 3));
    $this->signIn($h->t)->get(route('teacher.courses.quiz-report.export', ['course' => $h->course, 'quiz' => $h->quiz]))->assertOk();
    $other = taiKhoan('gv-khac-p6q@edunest.test', RoleCode::GiaoVien);
    $this->signIn($other)->get(route('teacher.courses.quiz-report.export', ['course' => $h->course, 'quiz' => $h->quiz]))->assertForbidden();
});
