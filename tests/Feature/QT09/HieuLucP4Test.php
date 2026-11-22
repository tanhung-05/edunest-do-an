<?php

use App\Actions\M10\ContinueQuizAttempt;
use App\Actions\M10\SubmitQuizAttempt;
use App\Enums\M05\CourseVersionStatus;
use App\Enums\M10\AttemptCloseReason;
use App\Enums\M10\AttemptStatus;
use App\Policies\M10\CertificatePolicy;
use App\Support\DomainRejection;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;

pest()->group('P4');

beforeEach(function () {
    $this->travelTo(HocTap::at('09:00:00'));
    Storage::fake('local');
    $this->h = new HocTap;
    $this->travelTo(HocTap::at('09:55:00'));
});

it('AC-15-04 Còn hạn mở bài, tải và bắt đầu quiz; hết hạn hoặc thu hồi bị chặn', function (string $validity, int $expected) {
    $h = $this->h;
    $h->signIn($this);
    $url = $this->get($h->lessonUrl())->assertOk()->viewData('page')['props']['resources'][0]['url'];
    match ($validity) {
        'expired' => $h->enrollment->update(['het_han_luc' => now()]),
        'revoked' => $h->enrollment->update(['revoked_at' => now()]),
        default => null,
    };
    $this->getJson($h->lessonUrl())->assertStatus($expected);
    $this->getJson($url)->assertStatus($expected);
    $this->post($h->quizUrl('attempts.store'))->assertStatus($expected === 200 ? 302 : 403);
})->with([['active', 200], ['expired', 403], ['revoked', 403]]);

it('AC-15-05 Bài và tài nguyên cùng bị khóa đúng giây hết hạn', function () {
    $h = $this->h;
    $h->enrollment->update(['het_han_luc' => HocTap::at('10:00:00')]);
    $h->signIn($this);
    $this->travelTo(HocTap::at('09:59:59'));
    $url = $this->get($h->lessonUrl())->assertOk()->viewData('page')['props']['resources'][0]['url'];
    $this->get($url)->assertOk();
    $this->travelTo(HocTap::at('10:00:00'));
    $this->getJson($h->lessonUrl())->assertForbidden();
    $this->getJson($url)->assertForbidden();
});

it('AC-15-06 Mở, lưu và nộp lượt cũ sau khi ghi danh hết hạn', function () {
    $h = $this->h;
    $h->quiz->update(['duration_minutes' => 25]);
    $h->enrollment->update(['het_han_luc' => HocTap::at('10:00:00')]);
    $attempt = $h->start();
    $this->travelTo(HocTap::at('10:05:00'));
    expect(app(ContinueQuizAttempt::class)->handle($h->a, $attempt)->status)->toBe(AttemptStatus::DangLam);
    $this->travelTo(HocTap::at('10:15:00'));
    $h->fill($attempt, 2);
    $this->travelTo(HocTap::at('10:19:59'));
    expect(app(SubmitQuizAttempt::class)->handle($h->a, $attempt)->score_earned)->toBe('2.0000');
});

it('AC-15-07 Không bắt đầu lượt mới khi ghi danh hết hạn và không lưu sau hạn lượt', function () {
    $h = $this->h;
    $h->quiz->update(['duration_minutes' => 25]);
    $h->enrollment->update(['het_han_luc' => HocTap::at('10:00:00')]);
    $attempt = $h->start();
    $this->travelTo(HocTap::at('10:20:01'));
    expect(fn () => $h->fill($attempt, 1))->toThrow(DomainRejection::class);
    expect($attempt->refresh()->close_reason)->toBe(AttemptCloseReason::HetGio);
    expect(fn () => $h->start())->toThrow(DomainRejection::class, 'Đã hết hạn truy cập.');
});

it('AC-15-08 Đạt sau hết hạn ghi nhận tiến độ, không đạt không hoàn thành quiz', function (int $correct, bool $expected) {
    $h = $this->h;
    $h->quiz->update(['duration_minutes' => 30]);
    $h->enrollment->update(['het_han_luc' => HocTap::at('10:00:00')]);
    $h->mark($h->video);
    $h->mark($h->read);
    $attempt = $h->start();
    $this->travelTo(HocTap::at('10:15:00'));
    $h->fill($attempt, $correct);
    expect(app(SubmitQuizAttempt::class)->handle($h->a, $attempt)->passed)->toBe($expected)
        ->and($h->enrollment->certificate()->exists())->toBe($expected);
})->with([[2, false], [3, true]]);

it('AC-15-09 Mọi hiệu lực ghi danh đều đọc được lịch sử của mình', function (array $fields) {
    $h = $this->h;
    $attempt = $h->submit();
    $h->enrollment->update($fields);
    $h->signIn($this)->get(route('student.attempts.result', $attempt))->assertOk();
    $this->get($h->quizUrl('history'))->assertOk();
    $this->get(route('student.orders.index'))->assertOk();
})->with([
    'còn hạn' => [[]],
    'hết hạn' => fn () => [['het_han_luc' => HocTap::at('09:55:00')]],
    'thu hồi' => fn () => [['revoked_at' => HocTap::at('09:55:00')]],
]);

it('AC-15-10 Quyền xem và tải chứng nhận không phụ thuộc hiệu lực ghi danh', function () {
    $h = $this->h;
    $certificate = $h->enrollment->certificate()->create([
        'certificate_number' => '2026-7KQ4M9XB', 'holder_name' => $h->a->name, 'course_title' => $h->course->title,
        'issued_on' => '2026-11-18', 'completion_event_id' => 'HT-'.$h->enrollment->id,
        'pdf_status' => 'DA_TAO', 'pdf_path' => 'certificates/p4.pdf',
    ]);
    $policy = app(CertificatePolicy::class);
    foreach ([[], ['het_han_luc' => now()], ['revoked_at' => now()]] as $fields) {
        $h->enrollment->update($fields);
        expect($policy->view($h->a, $certificate->refresh()))->toBeTrue()
            ->and($policy->download($h->a, $certificate))->toBeTrue();
    }
});

it('AC-15-11 URL video còn hạn vẫn kiểm lại mốc hết hạn ghi danh', function () {
    $h = $this->h;
    $h->enrollment->update(['het_han_luc' => HocTap::at('10:05:00')]);
    $h->signIn($this);
    $url = $this->get($h->lessonUrl())->assertOk()->viewData('page')['props']['video_url'];
    $this->travelTo(HocTap::at('10:04:00'));
    $this->get($url)->assertOk();
    $this->travelTo(HocTap::at('10:06:00'));
    $this->getJson($url)->assertForbidden();
});

it('AC-15-12 Ghi danh cũ được mở đúng bài bản cũ, ghi danh mới không dùng bài đó', function () {
    $h = $this->h;
    $h->version->update(['status' => CourseVersionStatus::DaThayThe]);
    $v = $h->course->versions()->create(['version_no' => 2, 'status' => CourseVersionStatus::DaCongBo, 'published_at' => now()]);
    $b = HocTap::student('ban-moi');
    $h->enroll($b, $v);
    $h->signIn($this)->get($h->lessonUrl())->assertOk();
    $this->flushSession();
    session()->migrate(true);
    $h->signIn($this, $b)->getJson($h->lessonUrl())->assertForbidden();
});

it('AC-15-13 Thu hồi dựng sẵn chốt lượt cũ và chặn bắt đầu lượt khác', function () {
    $h = $this->h;
    $attempt = $h->start();
    $h->enrollment->update(['revoked_at' => now()]);
    expect(app(ContinueQuizAttempt::class)->handle($h->a, $attempt)->close_reason)->toBe(AttemptCloseReason::ThuHoi);
    expect(fn () => $h->fill($attempt, 1))->toThrow(DomainRejection::class);
    expect(fn () => $h->start())->toThrow(DomainRejection::class, 'Quyền học đã bị thu hồi.');
});

it('AC-15-58 Ghi danh vĩnh viễn vẫn bị chặn file khi thu hồi', function () {
    $h = $this->h;
    expect($h->enrollment->het_han_luc)->toBeNull();
    $h->signIn($this);
    $url = $this->get($h->lessonUrl())->assertOk()->viewData('page')['props']['video_url'];
    $this->get($url)->assertOk();
    $h->enrollment->update(['revoked_at' => now()]);
    $this->getJson($url)->assertForbidden();
});

it('AC-09-42 Thu hồi quyền học bằng thao tác Giáo vụ')->todo('P5: thao tác thu hồi thực');
it('AC-09-43 URL video sau thao tác thu hồi thật')->todo('P5: thao tác thu hồi thực');
it('AC-09-44 URL tài nguyên sau thao tác thu hồi thật')->todo('P5: thao tác thu hồi thực');
