<?php

use App\Actions\M10\GrantExtraQuizAttempt;
use App\Actions\M10\RevokeCertificate;
use App\Enums\M01\RoleCode;
use App\Enums\M05\CourseVersionStatus;
use App\Enums\M07\OrderSource;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentChannel;
use App\Enums\M07\PaymentReconcileStatus;
use App\Enums\M07\PaymentTxnStatus;
use App\Enums\M08\LearningStatus;
use App\Enums\M10\AttemptCloseReason;
use App\Enums\M10\AttemptStatus;
use App\Jobs\M12\AggregateDailyReportMetrics;
use App\Models\M04\Quiz;
use App\Models\M05\CourseVersion;
use App\Models\M07\Order;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use App\Models\M11\AuditLog;
use App\Models\M12\ReportMetric;
use App\Queries\M12\ReportData;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\HocTap;
use Tests\Support\KhoaHoc;

pest()->group('P4');

beforeEach(function () {
    $this->travelTo(HocTap::at('00:10:00'));
    Storage::fake('local');
    $this->h = new HocTap;
});

function donP4(HocTap $h, OrderStatus $status = OrderStatus::HoanTat): Order
{
    return Order::query()->forceCreate([
        'user_id' => $h->a->id, 'public_code' => 'EDN-'.Str::upper(Str::random(10)),
        'source' => OrderSource::MuaNgay, 'status' => $status, 'subtotal' => 499000, 'total' => 499000,
        'expires_at' => now()->addMinutes(30),
    ]);
}

function khoanP4(HocTap $h, int $amount, ?CarbonImmutable $time = null, PaymentReconcileStatus $status = PaymentReconcileStatus::HopLe): void
{
    $time ??= now();
    donP4($h)->payments()->create([
        'transaction_code' => 'GD-'.Str::upper(Str::random(12)), 'channel' => PaymentChannel::MoPhong,
        'requested_amount' => $amount, 'received_amount' => $amount, 'txn_status' => PaymentTxnStatus::ThanhCong,
        'reconcile_status' => $status, 'terms_accepted_at' => $time, 'succeeded_at' => $time, 'reconciled_at' => $time,
        ...($status === PaymentReconcileStatus::DaHoanLai ? [
            'refunded_at' => $time, 'refunded_by_id' => $h->g->id, 'refund_reason' => 'Hoàn tiền mô phỏng.',
        ] : []),
    ]);
}

function demP4(int $period = 0): AggregateDailyReportMetrics
{
    $job = AggregateDailyReportMetrics::forDay(HocTap::at('02:00:00')->addDays($period));
    $job->handle();

    return $job;
}

function luotP4(HocTap $h, int $score, ?Enrollment $enrollment = null, ?Quiz $quiz = null, bool $open = false): QuizAttempt
{
    $enrollment ??= $h->enrollment;
    $quiz ??= $h->quiz;
    $base = $enrollment->quizAttempts()->where('quiz_id', $quiz->id);

    return $enrollment->quizAttempts()->create([
        'quiz_id' => $quiz->id, 'user_id' => $enrollment->user_id, 'attempt_no' => ((int) $base->max('attempt_no')) + 1,
        'status' => $open ? AttemptStatus::DangLam : AttemptStatus::DaNop,
        'close_reason' => $open ? null : AttemptCloseReason::Nop, 'started_at' => now()->subMinute(),
        'deadline_at' => now()->addMinutes(9), 'closed_at' => $open ? null : now(), 'finalized_at' => $open ? null : now(),
        'score_earned' => $open ? null : $score, 'score_max' => $open ? null : 100,
        'passed' => $open ? null : $score >= 70,
    ]);
}

function baoCaoQuizP4(HocTap $h, ?Quiz $quiz = null): array
{
    $quiz ??= $h->quiz;

    return test()->signIn($h->t)->get(route('teacher.courses.quiz-report', [
        'course' => $h->course, 'version' => $quiz->course_version_id, 'quiz' => $quiz->id,
    ]))->assertOk()->viewData('page')['props']['summary'];
}

it('AC-12-01 Giáo vụ xem doanh thu, đơn, ghi danh và tỷ lệ đúng mốc', function () {
    $h = $this->h;
    foreach ([499000, 499000, 299000] as $amount) {
        khoanP4($h, $amount);
    }
    $h->enrollment->update(['learning_status' => LearningStatus::HoanThanh, 'completed_at' => now()]);
    for ($i = 1; $i <= 3; $i++) {
        $h->enroll(HocTap::student("ghi-danh-$i"));
    }
    $job = demP4();
    $this->signIn($h->g)->get(route('academic.reports.index', ['course' => $h->course->id]))->assertInertia(fn ($page) => $page
        ->where('revenue.data.value.total', 1297000)->where('revenue.data.value.label', 'Mô phỏng')
        ->where('revenue.data.computed_at', $job->cutoffAt->toISOString())->where('orders.data.HOAN_TAT', 3)
        ->where('enrollments.data.CON_HAN', 4)->where('completion.data.value.percent', '25.0'));
});

it('AC-12-02 Tiền chờ hoàn và đã hoàn không tính doanh thu', function () {
    $h = $this->h;
    khoanP4($h, 499000);
    khoanP4($h, 299000, status: PaymentReconcileStatus::CanHoan);
    khoanP4($h, 199000, status: PaymentReconcileStatus::DaHoanLai);
    demP4();
    expect(app(ReportData::class)->latest('revenue', 'TOAN_HE_THONG')['value']['total'])->toBe(499000);
});

it('AC-12-03 Job chạy trễ giữ mốc 02:00 của kỳ', function () {
    $h = $this->h;
    khoanP4($h, 499000, HocTap::at('01:59:59'));
    khoanP4($h, 299000, HocTap::at('02:00:01'));
    $this->travelTo(HocTap::at('11:00:00'));
    demP4();
    $report = app(ReportData::class);
    expect($report->latest('revenue', 'TOAN_HE_THONG')['value']['total'])->toBe(499000);
    demP4(1);
    expect($report->latest('revenue', 'TOAN_HE_THONG')['value']['total'])->toBe(798000);
});

it('AC-12-04 Đếm đủ trạng thái đơn, kể cả nhóm 0', function () {
    $h = $this->h;
    foreach ([5, 1, 2, 1, 1] as $i => $count) {
        $status = [OrderStatus::HoanTat, OrderStatus::DangCapQuyen, OrderStatus::CanXuLy, OrderStatus::DaHuy, OrderStatus::HetHan][$i];
        for ($j = 0; $j < $count; $j++) {
            donP4($h, $status);
        }
    }
    expect(app(ReportData::class)->orders())->toMatchArray([
        'HOAN_TAT' => 5, 'DANG_CAP_QUYEN' => 1, 'CAN_XU_LY' => 2, 'DA_HUY' => 1, 'HET_HAN' => 1, 'CHO_THANH_TOAN' => 0,
    ]);
});

it('AC-12-05 Ghi danh được đếm theo hiệu lực hiện hành', function () {
    $h = $this->h;
    $h->enroll(HocTap::student('het-han'))->update(['het_han_luc' => now()]);
    $h->enroll(HocTap::student('thu-hoi'))->update(['revoked_at' => now()]);
    expect(app(ReportData::class)->enrollments())->toBe(['CON_HAN' => 1, 'HET_HAN' => 1, 'DA_THU_HOI' => 1]);
});

it('AC-12-06 Mẫu số hoàn thành gồm cả ghi danh hết hạn và bị thu hồi', function () {
    $h = $this->h;
    $h->enrollment->update(['learning_status' => LearningStatus::HoanThanh, 'completed_at' => now()]);
    $h->enroll(HocTap::student('het-han'))->update(['het_han_luc' => now()]);
    $h->enroll(HocTap::student('thu-hoi'))->update(['revoked_at' => now()]);
    demP4();
    expect(app(ReportData::class)->latest('completion_rate', 'KHOA_HOC', $h->course->id)['value'])
        ->toMatchArray(['total' => 3, 'completed' => 1, 'percent' => '33.3']);
});

it('AC-12-07 Khóa không ghi danh chưa đủ dữ liệu, số đơn thật bằng 0', function () {
    $h = $this->h;
    $h->enrollment->delete();
    demP4();
    $this->signIn($h->g)->get(route('academic.reports.index', ['course' => $h->course->id]))->assertInertia(fn ($page) => $page
        ->where('completion.data.value.total', 0)->where('completion.data.value.percent', null)->where('orders.data.HOAN_TAT', 0));
});

it('AC-12-08 Tỷ lệ 1/3 hiển thị 33,3 và giữ tử mẫu', function () {
    $h = $this->h;
    $h->enrollment->update(['learning_status' => LearningStatus::HoanThanh, 'completed_at' => now()]);
    $h->enroll(HocTap::student('hai'));
    $h->enroll(HocTap::student('ba'));
    demP4();
    $value = ReportMetric::query()->where('metric', 'completion_rate')->sole()->value;
    expect($value)->toMatchArray(['total' => 3, 'completed' => 1, 'percent' => '33.3']);
});

it('AC-12-09 Hoàn thành bản thay thế vẫn tính theo khóa gốc', function () {
    $h = $this->h;
    $h->enrollment->update(['learning_status' => LearningStatus::HoanThanh, 'completed_at' => now()]);
    $h->version->update(['status' => CourseVersionStatus::DaThayThe]);
    $v2 = $h->course->versions()->create(['version_no' => 2, 'status' => CourseVersionStatus::DaCongBo, 'published_at' => now()]);
    $h->course->update(['current_version_id' => $v2->id]);
    $h->enroll(HocTap::student('ban-hai'), $v2);
    demP4();
    expect(ReportMetric::query()->where('metric', 'completion_rate')->sole()->value)->toMatchArray(['completed' => 1, 'total' => 2]);
});

it('AC-12-10 Một khối lỗi không thay số liệu phần còn lại', function () {
    $h = $this->h;
    khoanP4($h, 499000);
    demP4();
    DB::connection()->beforeExecuting(function (string $query): void {
        if (str_contains($query, 'from `orders` group by')) {
            throw new RuntimeException('Lỗi số đơn');
        }
    });
    $this->signIn($h->g)->get(route('academic.reports.index'))->assertInertia(fn ($page) => $page
        ->where('orders.state', 'error')->where('orders.data', null)->where('revenue.data.value.total', 499000));
});

it('AC-12-11 Lỗi lần tổng hợp giữ dữ liệu kỳ trước, retry 1/5/15 phút', function () {
    $h = $this->h;
    khoanP4($h, 499000);
    $old = demP4();
    khoanP4($h, 299000, HocTap::at('03:00:00'));
    $new = AggregateDailyReportMetrics::forDay(HocTap::at('02:00:00')->addDay());
    $options = new ReflectionClass($new);
    expect($options->getAttributes(Backoff::class)[0]->getArguments()[0])->toBe([60, 300, 900])
        ->and($options->getAttributes(Tries::class)[0]->getArguments()[0])->toBe(4);
    $new = unserialize(serialize($new));
    expect($new->cutoffAt->equalTo($old->cutoffAt->addDay()))->toBeTrue();
    DB::connection()->beforeExecuting(function (string $query): void {
        if (str_starts_with($query, 'insert into `report_metrics`')) {
            throw new RuntimeException('Lỗi ghi số liệu mới');
        }
    });
    for ($try = 0; $try < 4; $try++) {
        expect(fn () => $new->handle())->toThrow(RuntimeException::class, 'Lỗi ghi số liệu mới');
    }
    $draft = app(ReportData::class)->latest('revenue', 'TOAN_HE_THONG');
    expect($draft['value']['total'])->toBe(499000)->and($draft['computed_at'])->toBe($old->cutoffAt->toISOString());
});

it('AC-12-12 Giáo viên và học viên không đọc doanh thu', function () {
    foreach ([$this->h->t, $this->h->a] as $user) {
        $this->signIn($user)->getJson(route('admin.revenue.index'))->assertForbidden();
        $this->getJson(route('academic.reports.index'))->assertForbidden();
    }
});

it('AC-12-13 Quản trị chỉ nhận số doanh thu và mốc cập nhật', function () {
    khoanP4($this->h, 499000);
    demP4();
    $admin = taiKhoan('admin-p4@edunest.test', RoleCode::QuanTri);
    $this->signIn($admin)->get(route('admin.revenue.index'))->assertInertia(fn ($page) => $page
        ->where('revenue.data.value.total', 499000)->missing('orders')->missing('enrollments')->missing('completion'));
    $this->getJson(route('academic.reports.index'))->assertForbidden();
});

it('AC-12-14 Trung bình dùng điểm ghi nhận tốt nhất của từng học viên', function () {
    $h = $this->h;
    luotP4($h, 50);
    luotP4($h, 80);
    luotP4($h, 60, $h->enroll(HocTap::student('b')));
    luotP4($h, 70, $h->enroll(HocTap::student('c')));
    $h->enroll(HocTap::student('d'));
    demP4();
    expect(baoCaoQuizP4($h)['data']['value']['average_percent'])->toBe('70.0');
});

function thongKeCauP4(HocTap $h, array $answers, bool $open = false): array
{
    $question = $h->quiz->items()->firstOrFail();
    foreach ($answers as $i => $correct) {
        $attempt = luotP4($h, 50, $h->enroll(HocTap::student("mau-$i")));
        if ($correct !== null) {
            $attempt->answers()->create([
                'quiz_question_id' => $question->id, 'answer' => [$question->snapshot['options'][1]['id']],
                'received_at' => now(), 'is_correct' => $correct,
            ]);
        }
    }
    if ($open) {
        luotP4($h, 0, open: true);
        luotP4($h, 0, $h->enroll(HocTap::student('dang-lam')), open: true);
    }
    demP4();

    return collect(baoCaoQuizP4($h)['data']['value']['most_incorrect_questions'])->firstWhere('quiz_question_id', $question->id);
}

it('AC-12-15 Chỉ tính lượt đã chốt và tách số bỏ trống', function () {
    expect(thongKeCauP4($this->h, [true, true, true, true, true, false, false, null], true))
        ->toMatchArray(['finalized_attempts' => 8, 'incorrect_attempts' => 3, 'blank_attempts' => 1]);
});

it('AC-12-16 Câu sai bao gồm câu bỏ trống', function () {
    expect(thongKeCauP4($this->h, [true, true, true, true, true, true, false, false, false, null]))
        ->toMatchArray(['finalized_attempts' => 10, 'incorrect_attempts' => 4, 'blank_attempts' => 1]);
});

it('AC-12-17 Xếp câu sai giảm dần và hòa theo thứ tự câu', function () {
    $h = $this->h;
    $attempt = luotP4($h, 10);
    $q = $h->quiz->items()->firstOrFail();
    $attempt->answers()->create(['quiz_question_id' => $q->id, 'answer' => [$q->snapshot['options'][1]['id']], 'received_at' => now(), 'is_correct' => true]);
    demP4();
    expect(array_column(baoCaoQuizP4($h)['data']['value']['most_incorrect_questions'], 'position'))->toBe([2, 3, 1]);
});

function quizBanHaiP4(HocTap $h): Quiz
{
    $h->version->update(['status' => CourseVersionStatus::DaThayThe]);
    $v = $h->course->versions()->create(['version_no' => 2, 'status' => CourseVersionStatus::DaCongBo, 'published_at' => now()]);
    $lesson = $v->sections()->create(['title' => 'Chương mới', 'position' => 1])->lessons()->create([
        'course_version_id' => $v->id, 'lesson_key' => $h->quizLesson->lesson_key,
        'title' => $h->quizLesson->title, 'type' => $h->quizLesson->type, 'position' => 1, 'is_required' => true,
    ]);
    $q = $lesson->quiz()->create(['course_version_id' => $v->id, 'kind' => $h->quiz->kind]);
    $source = $h->quiz->items()->firstOrFail();
    $q->items()->create(['question_id' => $source->question_id, 'position' => 1, 'points' => 1,
        'snapshot' => $source->snapshot, 'snapshotted_at' => now()]);

    return $q;
}

it('AC-12-18 Tách điểm và số lượt theo phiên bản', function () {
    $h = $this->h;
    $q2 = quizBanHaiP4($h);
    luotP4($h, 30);
    luotP4($h, 80, $h->enroll(HocTap::student('v2'), CourseVersion::findOrFail($q2->course_version_id)), $q2);
    demP4();
    expect(baoCaoQuizP4($h, $q2)['data']['value']['average_percent'])->toBe('80.0')
        ->and(baoCaoQuizP4($h)['data']['value']['average_percent'])->toBe('30.0');
});

it('AC-12-19 Cùng câu nguồn ở hai bản vẫn báo cáo theo từng bản chụp', function () {
    $h = $this->h;
    $q2 = quizBanHaiP4($h);
    luotP4($h, 30);
    luotP4($h, 70, $h->enroll(HocTap::student('v2'), CourseVersion::findOrFail($q2->course_version_id)), $q2);
    demP4();
    $q = $q2->items()->firstOrFail();
    expect(array_column(baoCaoQuizP4($h, $q2)['data']['value']['most_incorrect_questions'], 'quiz_question_id'))->toBe([$q->id]);
    expect(array_column(baoCaoQuizP4($h)['data']['value']['most_incorrect_questions'], 'quiz_question_id'))->not->toContain($q->id);
});

it('AC-12-20 Quiz chưa chốt lượt nào chưa đủ dữ liệu', function () {
    luotP4($this->h, 0, open: true);
    demP4();
    expect(baoCaoQuizP4($this->h)['data']['value'])->toBe(['average_percent' => null, 'most_incorrect_questions' => []]);
});

it('AC-12-21 Giáo viên không được giao khóa bị từ chối báo cáo', function () {
    $this->signIn(taiKhoan('khac-p4@edunest.test', RoleCode::GiaoVien))
        ->getJson(route('teacher.courses.quiz-report', $this->h->course))->assertForbidden();
});

it('AC-12-22 Học viên không đọc báo cáo quiz của giáo viên', function () {
    $this->signIn($this->h->a)->getJson(route('teacher.courses.quiz-report', $this->h->course))->assertForbidden();
});

it('AC-12-23 Giáo vụ thấy tiến độ tổng hợp và bị chặn bài làm riêng', function () {
    $h = $this->h;
    $h->mark($h->video);
    $h->mark($h->read);
    $attempt = $h->submit(1);
    $this->signIn($h->g)->get(route('academic.students.show', $h->a))->assertInertia(fn ($page) => $page
        ->where('student.email', $h->a->email)->where('enrollments.0.percent', '66.7')->missing('enrollments.0.answers'));
    $this->getJson(route('student.attempts.result', $attempt))->assertForbidden();
});

it('AC-12-24 Giáo viên không mở hồ sơ ghi danh của học viên', function () {
    $this->signIn($this->h->t)->getJson(route('academic.students.show', $this->h->a))->assertForbidden();
    $this->getJson(route('academic.enrollments.index'))->assertForbidden();
});

it('AC-12-27 Thu hồi chứng nhận và cấp thêm lượt mỗi việc ghi một nhật ký', function () {
    $h = $this->h;
    $h->quiz->update(['max_attempts' => 1]);
    $h->submit(1);
    $certificate = $h->enrollment->certificate()->create([
        'certificate_number' => '2026-7KQ4M9XB', 'holder_name' => $h->a->name, 'course_title' => $h->course->title,
        'issued_on' => '2026-11-18', 'completion_event_id' => 'HT-'.$h->enrollment->id,
    ]);
    app(RevokeCertificate::class)->handle($h->g, $certificate, 'Gian lận');
    app(GrantExtraQuizAttempt::class)->handle($h->t, $h->enrollment, $h->quiz, 'Cho làm lại');
    expect(AuditLog::query()->where('reason', 'Cho làm lại')->sole()->actor_id)->toBe($h->t->id);
    expect(AuditLog::query()->where('reason', 'Gian lận')->sole()->actor_id)->toBe($h->g->id);
});

it('AC-12-28 Quản trị thấy nhật ký có người và giá trị cũ mới', function () {
    $log = AuditLog::query()->where('action', 'VERSION_PUBLISH')->firstOrFail();
    $admin = taiKhoan('admin-p4@edunest.test', RoleCode::QuanTri);
    $this->signIn($admin)->get(route('admin.audit-logs.index', ['action' => 'VERSION_PUBLISH']))
        ->assertInertia(fn ($page) => $page->where('logs.data.0.actor_id', $log->actor_id)
            ->where('logs.data.0.old_values.status', 'DA_DUYET')->where('logs.data.0.new_values.status', 'DA_CONG_BO'));
});

it('AC-12-29 Giáo vụ, Giáo viên và Học viên không xem nhật ký', function () {
    foreach ([$this->h->g, $this->h->t, $this->h->a] as $user) {
        $this->signIn($user)->getJson(route('admin.audit-logs.index'))->assertForbidden();
    }
});

it('AC-12-30 Nhật ký không có tuyến sửa và xóa', function () {
    $log = AuditLog::query()->firstOrFail();
    $admin = taiKhoan('admin-p4@edunest.test', RoleCode::QuanTri);
    $this->signIn($admin)->patchJson('/admin/audit-logs/'.$log->id, ['action' => 'KHAC'])->assertNotFound();
    $this->deleteJson('/admin/audit-logs/'.$log->id)->assertNotFound();
    expect($log->refresh()->action)->not->toBe('KHAC');
});

it('AC-12-31 Lịch vận hành có hai tác vụ phút và tổng hợp 02:00 giờ Việt Nam', function () {
    $events = collect(app(Schedule::class)->events());
    $daily = $events->first(fn ($event) => str_contains($event->command ?? '', 'learning:aggregate-daily-report'));
    expect($daily->expression)->toBe('0 2 * * *')->and($daily->timezone)->toBe('Asia/Ho_Chi_Minh');
    foreach (['learning:finalize-overdue-attempts', 'learning:recover-completions'] as $command) {
        expect($events->first(fn ($event) => str_contains($event->command ?? '', $command))->expression)->toBe('* * * * *');
    }
});

it('AC-12-32 Báo cáo quiz chỉ đổi khi đến kỳ tổng hợp kế tiếp', function () {
    $h = $this->h;
    luotP4($h, 70);
    demP4();
    $this->travelTo(HocTap::at('09:00:00'));
    luotP4($h, 90, $h->enroll(HocTap::student('ngay-sau')));
    expect(baoCaoQuizP4($h)['data']['value']['average_percent'])->toBe('70.0');
    demP4(1);
    expect(baoCaoQuizP4($h)['data']['value']['average_percent'])->toBe('80.0');
});

it('AC-12-33 Chỉ hiện 10 câu sai nhất và hòa theo vị trí', function () {
    $this->travelTo(HocTap::at('00:10:00'));
    $h = $this->h;
    $bank = KhoaHoc::nganHang($h->course, $h->t, 'Câu mở rộng', 9);
    $snapshot = $h->quiz->items()->firstOrFail()->snapshot;
    foreach ($bank->questions()->get()->values() as $index => $question) {
        $h->quiz->items()->create(['question_id' => $question->id, 'position' => $index + 4, 'points' => 1, 'snapshot' => $snapshot]);
    }
    luotP4($h, 0);
    demP4();
    expect(array_column(baoCaoQuizP4($h)['data']['value']['most_incorrect_questions'], 'position'))->toBe(range(1, 10));
});

it('AC-12-34 Doanh thu gán ngày theo biên nửa đêm Việt Nam', function () {
    $h = $this->h;
    khoanP4($h, 499000, HocTap::at('00:00:00')->subSecond());
    khoanP4($h, 299000, HocTap::at('00:00:00'));
    demP4();
    expect(app(ReportData::class)->latest('revenue', 'TOAN_HE_THONG')['value']['by_payment_date'])
        ->toMatchArray(['2026-11-17' => 499000, '2026-11-18' => 299000]);
});
