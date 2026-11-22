<?php

use App\Actions\M01\LockAccount;
use App\Actions\M09\MarkLessonCompleted;
use App\Actions\M10\CompleteEnrollment;
use App\Actions\M10\EnsureCertificatePdfJob;
use App\Actions\M10\RecoverEnrollmentCompletions;
use App\Actions\M10\RevokeCertificate;
use App\Actions\M10\SubmitQuizAttempt;
use App\Enums\M01\RoleCode;
use App\Enums\M08\LearningStatus;
use App\Enums\M10\CertificatePdfStatus as PDF;
use App\Enums\M10\CertificateStatus as CN;
use App\Events\M10\EnrollmentCompleted;
use App\Jobs\M10\GenerateCertificatePdf;
use App\Jobs\M12\AggregateDailyReportMetrics;
use App\Models\M08\Enrollment;
use App\Models\M10\Certificate;
use App\Models\M11\AuditLog;
use App\Queries\M12\ReportData;
use App\Support\M10\CertificatePdfRenderer;
use App\Support\M10\CertificateVerification;
use App\Support\M10\MinimalCertificatePdfRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\HocTap;

pest()->group('P4');

beforeEach(function () {
    $this->travelTo(HocTap::at('09:00:00'));
    Storage::fake('local');
    $this->h = new HocTap;
    $this->h->a->update(['name' => 'Nguyễn Văn A']);
    $this->travelTo(HocTap::at('15:00:00'));
});

function cnP4(HocTap $h, ?Enrollment $enrollment = null): Certificate
{
    return ($enrollment ?? $h->enrollment)->certificate()->create([
        'certificate_number' => '2026-'.($enrollment === null ? '7KQ4M9XB' : '8KQ4M9XB'),
        'holder_name' => ($enrollment ?? $h->enrollment)->user()->firstOrFail()->name,
        'course_title' => $h->course->title,
        'issued_on' => '2026-11-18',
        'completion_event_id' => 'HT-'.($enrollment ?? $h->enrollment)->id,
    ]);
}

function pdfP4(Certificate $certificate): Certificate
{
    app(EnsureCertificatePdfJob::class)->handle($certificate->id);
    $job = Queue::connection('database')->pop('default');
    expect($job)->not->toBeNull()->and($job->uuid())->toBe($certificate->refresh()->pdf_job_token);
    $job->fire();
    $job->delete();

    return $certificate->refresh();
}

function hoanTatP4(HocTap $h): Certificate
{
    $h->mark($h->video);
    $h->submit(3);
    app(MarkLessonCompleted::class)->handle($h->a, $h->enrollment, $h->read);

    return $h->enrollment->certificate()->sole();
}

function taiP4(HocTap $h): ?string
{
    $h->signIn(test());

    return test()->get(route('student.certificates.index'))->assertOk()->viewData('page')['props']['certificates'][0]['download_url'];
}

it('AC-11-01 Bài cuối ghi hoàn thành, bản chụp và xếp Job PDF', function () {
    $h = $this->h;
    $h->quizLesson->update(['is_required' => false, 'is_final_test' => false]);
    $h->mark($h->video);
    app(MarkLessonCompleted::class)->handle($h->a, $h->enrollment, $h->read);
    $certificate = $h->enrollment->certificate()->sole();
    expect($certificate->status)->toBe(CN::HieuLuc)->and($certificate->pdf_status)->toBe(PDF::ChoTao)
        ->and($certificate->holder_name)->toBe('Nguyễn Văn A')->and($certificate->issued_on->toDateString())->toBe('2026-11-18')
        ->and($h->enrollment->refresh()->completed_at->equalTo(now()))->toBeTrue();
    $this->artisan('learning:recover-completions')->assertSuccessful();
    expect(DB::table('jobs')->where('queue', 'default')->count())->toBe(1);
});

it('AC-11-02 Thiếu bài bắt buộc thì chưa cấp chứng nhận', function () {
    $h = $this->h;
    app(MarkLessonCompleted::class)->handle($h->a, $h->enrollment, $h->read);
    expect($h->enrollment->refresh()->learning_status)->toBe(LearningStatus::DangHoc)
        ->and($h->enrollment->certificate()->exists())->toBeFalse();
});

it('AC-11-03 Điểm chính xác của bài kiểm tra cuối quyết định chứng nhận', function () {
    $h = $this->h;
    $h->mark($h->read);
    $h->mark($h->video);
    $questions = $h->quiz->items()->get();
    foreach ([['69.96', '30.03', false], ['70', '29.99', true]] as [$earned, $lost, $expected]) {
        $questions[0]->update(['points' => $earned]);
        $questions[1]->update(['points' => $lost]);
        $questions[2]->update(['points' => '0.01']);
        $h->submit(1);
        expect($h->enrollment->certificate()->exists())->toBe($expected);
    }
});

it('AC-11-05 Học lại không thay số chứng nhận', function () {
    $h = $this->h;
    $certificate = hoanTatP4($h);
    $this->travel(1)->hours();
    app(MarkLessonCompleted::class)->handle($h->a, $h->enrollment, $h->read);
    expect($h->enrollment->certificate()->sole()->id)->toBe($certificate->id);
});

it('AC-11-06 Hai ghi danh hoàn thành cùng lúc có mã khác nhau', function () {
    $h = $this->h;
    $first = hoanTatP4($h);
    $other = $h->enroll(HocTap::student('b'));
    $other->update(['learning_status' => LearningStatus::HoanThanh, 'completed_at' => now()]);
    $second = app(CompleteEnrollment::class)->handle($other);
    expect($first->certificate_number)->toMatch('/^2026-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/')
        ->and($second->certificate_number)->toMatch('/^2026-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/')
        ->not->toBe($first->certificate_number);
});

it('AC-11-07 Đổi tên sau khi cấp vẫn hiện bản chụp', function () {
    $h = $this->h;
    $certificate = hoanTatP4($h);
    $h->a->update(['name' => 'Nguyễn A']);
    $h->course->update(['title' => 'Hóa 12 nâng cao']);
    $this->get(CertificateVerification::url($certificate))->assertInertia(fn ($page) => $page
        ->where('certificate.holder_name', 'Nguyễn Văn A')->where('certificate.course_title', 'Hóa 12 nền tảng'));
});

it('AC-11-08 Làm nốt quiz sau hết hạn vẫn cấp chứng nhận nếu đạt', function () {
    $h = $this->h;
    $h->mark($h->read);
    $h->mark($h->video);
    $h->quiz->update(['duration_minutes' => 30]);
    $h->enrollment->update(['het_han_luc' => HocTap::at('10:00:00')]);
    $this->travelTo(HocTap::at('09:55:00'));
    $attempt = $h->start();
    $this->travelTo(HocTap::at('10:15:00'));
    $h->fill($attempt, 3);
    app(SubmitQuizAttempt::class)->handle($h->a, $attempt);
    expect($h->enrollment->refresh()->learning_status)->toBe(LearningStatus::HoanThanh)
        ->and($h->enrollment->certificate()->sole()->status)->toBe(CN::HieuLuc);
});

it('AC-11-09 Mã QR SVG và PDF dự phòng cùng dùng URL xác minh đúng số', function () {
    $certificate = cnP4($this->h);
    $url = route('public.certificates.show', '2026-7KQ4M9XB');
    $svg = CertificateVerification::qr($certificate);
    $bytes = (new MinimalCertificatePdfRenderer)->render($certificate);
    expect($svg)->toContain('<svg', '<path')->and(CertificateVerification::url($certificate))->toBe($url)
        ->and($bytes)->toStartWith('%PDF-1.4')->toContain($url, 'Nguyen Van A', '18/11/2026', 'xref');
});

it('AC-11-10 Hết lần thử chỉ đánh dấu PDF lỗi, chứng nhận giữ hiệu lực', function () {
    $certificate = cnP4($this->h);
    app(EnsureCertificatePdfJob::class)->handle($certificate->id);
    (new GenerateCertificatePdf($certificate->id, $certificate->refresh()->pdf_job_token))->failed(new RuntimeException);
    expect($certificate->refresh()->pdf_status)->toBe(PDF::Loi)->and($certificate->status)->toBe(CN::HieuLuc)
        ->and($certificate->pdf_job_token)->toBeNull()->and(taiP4($this->h))->toBeNull()
        ->and(Certificate::query()->count())->toBe(1);
});

it('AC-11-11 Giáo vụ tạo lại PDF cho đúng số chứng nhận', function () {
    $h = $this->h;
    $certificate = cnP4($h);
    $certificate->update(['pdf_status' => PDF::Loi]);
    $this->signIn($h->g)->post(route('academic.certificates.regenerate-pdf', $certificate))->assertRedirect();
    pdfP4($certificate);
    expect($certificate->certificate_number)->toBe('2026-7KQ4M9XB')->and($certificate->pdf_status)->toBe(PDF::DaTao);
});

it('AC-11-13 Hết hạn ghi danh vẫn tải chứng nhận hiệu lực', function () {
    $h = $this->h;
    pdfP4(cnP4($h));
    $h->enrollment->update(['het_han_luc' => now()]);
    $this->get(taiP4($h))->assertOk()->assertDownload('2026-7KQ4M9XB.pdf');
});

it('AC-11-14 Thu hồi ghi danh không thu hồi chứng nhận', function () {
    $h = $this->h;
    $certificate = pdfP4(cnP4($h));
    $h->enrollment->update(['revoked_at' => now()]);
    $this->get(CertificateVerification::url($certificate))->assertInertia(fn ($page) => $page->where('certificate.status', 'Hiệu lực'));
    $this->get(taiP4($h))->assertOk();
});

it('AC-11-15 Khách không tải được PDF riêng', function () {
    $certificate = pdfP4(cnP4($this->h));
    $this->get(route('student.certificates.download', $certificate))->assertRedirect(route('login'));
});

it('AC-11-16 Chủ khác không tải được PDF dù biết URL', function () {
    $h = $this->h;
    pdfP4(cnP4($h));
    $url = taiP4($h);
    $this->flushSession();
    session()->migrate(true);
    $h->signIn($this, HocTap::student('b'))->getJson($url)->assertForbidden();
});

it('AC-11-17 Khách xác minh được số hợp lệ', function () {
    $certificate = cnP4($this->h);
    $this->get(route('public.certificates.verify', ['number' => strtolower($certificate->certificate_number)]))
        ->assertRedirect(CertificateVerification::url($certificate));
    $this->get(CertificateVerification::url($certificate))->assertOk()->assertInertia(fn ($page) => $page
        ->component('public/certificates/show')->where('certificate.status', 'Hiệu lực')->where('certificate.issued_on', '2026-11-18'));
});

it('AC-11-18 Dữ liệu xác minh chỉ có năm trường nghiệp vụ', function () {
    $response = $this->get(CertificateVerification::url(cnP4($this->h)))->assertOk();
    expect(array_keys($response->viewData('page')['props']['certificate']))
        ->toBe(['holder_name', 'course_title', 'issued_on', 'number', 'status']);
});

it('AC-11-19 Số không tồn tại trả 404', function () {
    $this->getJson(route('public.certificates.show', '2026-ZZZZZZZZ'))->assertNotFound()->assertJsonPath('message', 'Không tìm thấy chứng nhận.');
});

it('AC-11-20 Lượt 31 bị giới hạn theo IP, hết cửa sổ được tra tiếp', function () {
    $url = CertificateVerification::url(cnP4($this->h));
    $this->travelTo(HocTap::at('10:00:00'));
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1']);
    for ($i = 0; $i < 30; $i++) {
        $this->get($url)->assertOk();
    }
    $this->travelTo(HocTap::at('10:00:50'));
    $this->getJson($url)->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->get($url)->assertOk();
    $this->travelTo(HocTap::at('10:01:01'));
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->get($url)->assertOk();
});

it('AC-11-21 Thu hồi ghi một nhật ký và cập nhật xác minh ngay', function () {
    $h = $this->h;
    $certificate = cnP4($h);
    $this->signIn($h->g)->post(route('academic.certificates.revoke', $certificate), ['reason' => 'Gian lận khi làm bài'])->assertRedirect();
    expect($certificate->refresh()->status)->toBe(CN::DaThuHoi)
        ->and(AuditLog::query()->where('reason', 'Gian lận khi làm bài')->sole()->actor_id)->toBe($h->g->id);
    $this->get(CertificateVerification::url($certificate))->assertInertia(fn ($page) => $page
        ->where('certificate.status', 'Đã thu hồi')->missing('certificate.revoked_reason'));
});

it('AC-11-22 Thu hồi thiếu lý do bị từ chối', function () {
    $h = $this->h;
    $certificate = cnP4($h);
    $this->signIn($h->g)->postJson(route('academic.certificates.revoke', $certificate), ['reason' => '   '])
        ->assertUnprocessable()->assertJsonValidationErrors('reason');
    expect($certificate->refresh()->status)->toBe(CN::HieuLuc);
});

it('AC-11-23 Học viên và Giáo viên không thu hồi chứng nhận', function () {
    $h = $this->h;
    $certificate = cnP4($h);
    foreach ([$h->a, $h->t] as $actor) {
        $this->signIn($actor)->postJson(route('academic.certificates.revoke', $certificate), ['reason' => 'Thử'])->assertForbidden();
    }
    expect($certificate->refresh()->status)->toBe(CN::HieuLuc);
});

it('AC-11-24 Số thu hồi vẫn xác minh được với nhãn thu hồi', function () {
    $h = $this->h;
    $certificate = cnP4($h);
    app(RevokeCertificate::class)->handle($h->g, $certificate, 'Gian lận');
    $this->get(CertificateVerification::url($certificate))->assertOk()->assertInertia(fn ($page) => $page->where('certificate.status', 'Đã thu hồi'));
});

it('AC-11-25 Thu hồi lặp không ghi nhật ký thứ hai', function () {
    $h = $this->h;
    $certificate = cnP4($h);
    $url = route('academic.certificates.revoke', $certificate);
    $this->signIn($h->g)->post($url, ['reason' => 'Lần đầu'])->assertRedirect();
    $this->postJson($url, ['reason' => 'Lần hai'])->assertUnprocessable();
    expect(AuditLog::query()->where('reason', 'Lần hai')->exists())->toBeFalse();
});

it('AC-11-26 Chạy hoàn thành lại không cấp bù chứng nhận bị thu hồi', function () {
    $h = $this->h;
    $certificate = hoanTatP4($h);
    app(RevokeCertificate::class)->handle($h->g, $certificate, 'Gian lận');
    $repeat = app(CompleteEnrollment::class)->handle($h->enrollment);
    expect($repeat->id)->toBe($certificate->id)->and($repeat->status)->toBe(CN::DaThuHoi);
});

it('AC-11-27 Cấp, render, tải PDF và mở trang xác minh', function () {
    $h = $this->h;
    $certificate = pdfP4(hoanTatP4($h));
    $this->get(taiP4($h))->assertOk()->assertDownload($certificate->certificate_number.'.pdf');
    $this->get(CertificateVerification::url($certificate))->assertInertia(fn ($page) => $page
        ->where('certificate.number', $certificate->certificate_number)->where('certificate.holder_name', 'Nguyễn Văn A'));
});

it('AC-11-29 Không tạo lại khi PDF đã tạo', function () {
    $h = $this->h;
    $certificate = pdfP4(cnP4($h));
    $path = $certificate->pdf_path;
    $this->signIn($h->g)->postJson(route('academic.certificates.regenerate-pdf', $certificate))->assertUnprocessable();
    expect($certificate->refresh()->pdf_path)->toBe($path);
});

it('AC-11-30 Học viên không yêu cầu tạo lại PDF', function () {
    $h = $this->h;
    $certificate = cnP4($h);
    $certificate->update(['pdf_status' => PDF::Loi]);
    $this->signIn($h->a)->postJson(route('academic.certificates.regenerate-pdf', $certificate))->assertForbidden();
    expect(DB::table('jobs')->count())->toBe(0);
});

it('AC-11-31 Tác vụ quét bù chứng nhận giữ thời điểm hoàn thành cũ', function () {
    $h = $this->h;
    $at = now();
    $h->enrollment->update(['learning_status' => LearningStatus::HoanThanh, 'completed_at' => $at]);
    $this->travel(1)->minutes();
    $this->artisan('learning:recover-completions')->assertSuccessful();
    $this->artisan('learning:recover-completions')->assertSuccessful();
    expect($h->enrollment->refresh()->completed_at->equalTo($at))->toBeTrue()->and($h->enrollment->certificate()->count())->toBe(1);
});

it('AC-11-32 Học tự do đạt bài kiểm tra trước rồi đủ bài mới cấp chứng nhận', function () {
    $h = $this->h;
    expect($h->submit(3)->passed)->toBeTrue()->and($h->enrollment->certificate()->exists())->toBeFalse();
    $h->mark($h->video);
    app(MarkLessonCompleted::class)->handle($h->a, $h->enrollment, $h->read);
    expect($h->enrollment->certificate()->sole()->status)->toBe(CN::HieuLuc);
});

it('AC-11-33 Thu hồi chứng nhận chặn cả URL tải đã ký', function () {
    $h = $this->h;
    $certificate = pdfP4(cnP4($h));
    $url = taiP4($h);
    app(RevokeCertificate::class)->handle($h->g, $certificate, 'Gian lận');
    expect(taiP4($h))->toBeNull();
    $this->getJson($url)->assertForbidden();
});

it('AC-11-34 Sinh 1.000 số theo bảng chữ và không trùng', function () {
    $suffix = new ReflectionMethod(CompleteEnrollment::class, 'suffix');
    $numbers = [];
    for ($i = 0; $i < 1000; $i++) {
        $number = now()->format('Y').'-'.$suffix->invoke(null);
        expect($number)->toMatch('/^2026-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/');
        $numbers[] = $number;
    }
    expect(array_unique($numbers))->toHaveCount(1000);
});

it('AC-11-35 Chốt do khóa tài khoản có thể hoàn thành khóa và cấp chứng nhận', function () {
    $h = $this->h;
    $h->mark($h->read);
    $h->mark($h->video);
    $attempt = $h->start();
    $h->fill($attempt, 3);
    app(LockAccount::class)->handle(taiKhoan('admin-p4@edunest.test', RoleCode::QuanTri), $h->a);
    expect($h->enrollment->certificate()->sole()->status)->toBe(CN::HieuLuc);
});

it('AC-11-36 Danh sách riêng chỉ trả chứng nhận của chủ', function () {
    $h = $this->h;
    cnP4($h);
    $other = HocTap::student('b');
    $certificate = cnP4($h, $h->enroll($other));
    $h->signIn($this, $other)->get(route('student.certificates.index'))
        ->assertInertia(fn ($page) => $page->has('certificates', 1)->where('certificates.0.number', $certificate->certificate_number));
    $this->getJson(route('student.certificates.index', ['user' => $h->a->id]))->assertForbidden();
});

it('AC-11-37 TV-17 phục hồi đúng vé, không xếp trùng và chỉ công bố một PDF', function () {
    $certificate = cnP4($this->h);
    $this->artisan('learning:recover-completions')->assertSuccessful();
    $token = $certificate->refresh()->pdf_job_token;
    $this->travel(1)->minutes();
    $this->artisan('learning:recover-completions')->assertSuccessful();
    expect($certificate->refresh()->pdf_job_token)->toBe($token)->and(DB::table('jobs')->count())->toBe(1);
    pdfP4($certificate);
    $this->artisan('learning:recover-completions')->assertSuccessful();
    expect(DB::table('jobs')->count())->toBe(0)->and(Storage::disk('local')->allFiles('certificates'))->toHaveCount(1);
});

it('AC-11-38 Lỗi chèn chứng nhận rollback cả hoàn thành bài và ghi danh', function () {
    $h = $this->h;
    $h->mark($h->video);
    $h->submit(3);
    Certificate::creating(fn () => throw new RuntimeException('Lỗi ghi chứng nhận'));
    try {
        expect(fn () => app(MarkLessonCompleted::class)->handle($h->a, $h->enrollment, $h->read))->toThrow(RuntimeException::class, 'Lỗi ghi chứng nhận');
    } finally {
        Certificate::flushEventListeners();
    }
    expect($h->enrollment->refresh()->learning_status)->toBe(LearningStatus::DangHoc)
        ->and($h->enrollment->lessonProgress()->where('lesson_id', $h->read->id)->exists())->toBeFalse()
        ->and($h->enrollment->certificate()->exists())->toBeFalse();
});

it('AC-11-39 Phát lại cùng mã sau mất dấu, báo cáo vẫn chỉ tính một ghi danh', function () {
    $h = $this->h;
    $h->enrollment->update(['learning_status' => LearningStatus::HoanThanh, 'completed_at' => now()]);
    $certificate = cnP4($h);
    $recovery = app(RecoverEnrollmentCompletions::class);
    $received = [];
    Event::listen(EnrollmentCompleted::class, function (EnrollmentCompleted $event) use (&$received, $h) {
        expect($h->enrollment->certificate()->exists())->toBeTrue();
        $received[] = $event->completionEventId;
        if (count($received) === 1) {
            throw new RuntimeException('Dừng trước khi lưu dấu đã phát');
        }
    });
    expect(fn () => $recovery->recover($certificate->id))->toThrow(RuntimeException::class);
    $this->travel(6)->minutes();
    $recovery->recover($certificate->id);
    $recovery->recover($certificate->id);
    expect($received)->toBe([$certificate->completion_event_id, $certificate->completion_event_id]);
    AggregateDailyReportMetrics::forDay(now()->addDay())->handle();
    expect(app(ReportData::class)->latest('completion_rate', 'KHOA_HOC', $h->course->id)['value']['completed'])->toBe(1);
});

it('AC-11-12 Gửi thông báo chứng nhận')->todo('P5: thông báo nhận chứng nhận');
it('AC-11-28 Gia hạn giữ nguyên chứng nhận')->todo('P5: thao tác gia hạn');
it('AC-11-40 Xét hoàn thành sau khi chuyển bản')->todo('P6: chuyển phiên bản');
it('AC-11-41 Chỉ xét phiên bản đang gắn')->todo('P6: chuyển phiên bản');
it('AC-11-42 Gán ngày nghiệp vụ sau chuyển bản')->todo('P6/P7: báo cáo và thưởng sau chuyển phiên bản');

it('DT-35 Worker cũ không xóa vé mới hoặc đổi PDF đã công bố', function () {
    $certificate = cnP4($this->h);
    $old = (string) Str::uuid();
    $certificate->update(['pdf_job_token' => $old]);
    pdfP4($certificate);
    (new GenerateCertificatePdf($certificate->id, $old))->failed(new RuntimeException);
    expect($certificate->refresh()->pdf_status)->toBe(PDF::DaTao)->and($certificate->pdf_job_token)->toBeNull();
});

it('DT-31 Thu hồi trong lúc render chặn công bố và dọn tệp tạm', function () {
    $h = $this->h;
    $certificate = cnP4($h);
    $token = (string) Str::uuid();
    $certificate->update(['pdf_job_token' => $token]);
    $renderer = Mockery::mock(CertificatePdfRenderer::class);
    $renderer->shouldReceive('render')->once()->andReturnUsing(function () use ($h, $certificate) {
        app(RevokeCertificate::class)->handle($h->g, $certificate, 'Thu hồi khi đang render');

        return '%PDF-1.4';
    });
    (new GenerateCertificatePdf($certificate->id, $token))->handle($renderer);
    expect($certificate->refresh()->status)->toBe(CN::DaThuHoi)->and($certificate->pdf_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles('certificates'))->toBe([]);
});
