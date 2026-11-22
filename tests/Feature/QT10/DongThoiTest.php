<?php

use App\Enums\M01\RoleCode;
use App\Enums\M03\LearningMode;
use App\Enums\M08\LearningStatus;
use App\Enums\M10\AttemptCloseReason as LyDo;
use App\Enums\M10\CertificatePdfStatus;
use App\Enums\M10\CertificateStatus;
use App\Models\M10\QuizAttemptGrant;
use App\Models\M11\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\HocTap;

pest()->group('P4', 'concurrency');

beforeEach(function () {
    $this->travelTo(HocTap::at('09:00:00'));
    Storage::fake('local');
    $this->h = new HocTap;
    $this->travelTo(HocTap::at('10:00:00'));
});

/** Cố định giờ hai tiến trình và nhả khóa sau khi tiến trình thứ hai đã vào ca. */
function giaoDichP4(array $first, string $barrier, array $second, bool $blocks = true, ?string $advance = null): array
{
    $db = DB::connection()->getDatabaseName();
    expect($db)->toStartWith('edunest_test');
    DB::commit();
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'edunest-p4-'.bin2hex(random_bytes(8));
    mkdir($dir);
    $env = [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $db, 'DB_URL' => '',
        'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        'EDUNEST_BARRIER_DIR' => $dir,
    ];
    $path = fn (string $file): string => $dir.DIRECTORY_SEPARATOR.$file;
    $launch = static function (array $scenario, string $side, string $point) use ($env): Process {
        $args = $scenario[1] + ['at' => now()->toISOString(), 'side' => $side];
        $process = new Process([PHP_BINARY, __DIR__.'/DongThoiRunner.php', $scenario[0], json_encode($args, JSON_THROW_ON_ERROR)],
            base_path(), $env + ['EDUNEST_BARRIER_POINT' => $point], null, 32);
        $process->start();

        return $process;
    };
    $await = static function (Process $process, string $file): void {
        $limit = microtime(true) + 14;
        while (! is_file($file)) {
            if (! $process->isRunning() || microtime(true) > $limit) {
                throw new RuntimeException('Tiến trình chưa đến điểm dừng: '.$process->getErrorOutput().$process->getOutput());
            }
            usleep(20000);
            clearstatcache(true, $file);
        }
    };
    $result = static function (Process $process): array {
        $process->wait();
        if (! $process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput().$process->getOutput());
        }

        return json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
    };

    $a = $b = null;
    try {
        $a = $launch($first, 'a', $barrier);
        $await($a, $path($barrier.'.reached'));
        $b = $launch($second, 'b', 'khong-dung');
        $await($b, $path('b.ready'));
        if ($blocks) {
            usleep(300000);
            expect($b->isRunning())->toBeTrue();
        } else {
            $result($b);
        }
        if ($advance !== null) {
            file_put_contents($path('clock'), HocTap::at($advance)->toISOString());
        }
        touch($path($barrier.'.release'));

        return [$result($a), $result($b)];
    } finally {
        $a?->stop();
        $b?->stop();
        foreach (glob($path('*')) ?: [] as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
}

it('AC-10-26 DT-06 Hai tab đồng thời nhận một lượt mở', function () {
    $h = $this->h;
    $start = ['start', ['actor' => $h->a->id, 'enrollment' => $h->enrollment->id, 'quiz' => $h->quiz->id]];
    [$a, $b] = giaoDichP4($start, 'start-quiz-attempt.enrollment-locked', $start);
    expect($a['value'])->toBe($b['value'])->and($h->enrollment->quizAttempts()->count())->toBe(1);
});

it('DT-07 Bắt đầu và khóa tài khoản tuần tự qua khóa người dùng', function (bool $lockFirst) {
    $h = $this->h;
    $admin = taiKhoan('admin-p4@edunest.test', RoleCode::QuanTri);
    $start = ['start', ['actor' => $h->a->id, 'enrollment' => $h->enrollment->id, 'quiz' => $h->quiz->id]];
    $lock = ['lock', ['actor' => $admin->id, 'target' => $h->a->id]];
    if ($lockFirst) {
        [, $b] = giaoDichP4($lock, 'lock-account.locked', $start);
        expect($b['status'])->toBe(403)->and($h->enrollment->quizAttempts()->count())->toBe(0);
    } else {
        giaoDichP4($start, 'start-quiz-attempt.enrollment-locked', $lock);
        expect($h->enrollment->quizAttempts()->sole()->close_reason)->toBe(LyDo::ThuHoi);
    }
})->with([true, false]);

it('AC-10-28 DT-08 Quét và nộp sau hạn giữ mốc quá giờ theo cả hai thứ tự', function (bool $scanFirst) {
    $h = $this->h;
    $attempt = $h->start();
    $h->fill($attempt, 1);
    $this->travelTo(HocTap::at('10:10:01'));
    $scan = ['finalize', ['attempt' => $attempt->id]];
    $submit = ['submit', ['actor' => $h->a->id, 'attempt' => $attempt->id]];
    if ($scanFirst) {
        giaoDichP4($scan, 'finalize-attempt.locked', $submit);
    } else {
        giaoDichP4($submit, 'quiz-attempt.locked', $scan);
    }
    expect($attempt->refresh()->close_reason)->toBe(LyDo::HetGio)->and($attempt->score_earned)->toBe('1.0000')
        ->and($attempt->closed_at->equalTo($attempt->deadline_at))->toBeTrue();
})->with([true, false]);

it('DT-08 Yêu cầu lưu đến trước hạn nhưng lấy khóa lượt sau hạn bị từ chối', function () {
    $h = $this->h;
    $attempt = $h->start();
    $question = $h->quiz->items()->firstOrFail();
    $this->travelTo(HocTap::at('10:09:59'));
    [, $b] = giaoDichP4(['finalize', ['attempt' => $attempt->id]], 'finalize-attempt.locked',
        ['save', ['actor' => $h->a->id, 'attempt' => $attempt->id, 'question' => $question->id,
            'answer' => [$question->snapshot['options'][1]['id']], 'follow_clock' => true]],
        advance: '10:10:01');
    expect($b['status'])->toBe(422)->and($attempt->refresh()->close_reason)->toBe(LyDo::HetGio);
});

it('DT-09 Nộp và khóa tài khoản giữ kết quả của bước thắng khóa', function (bool $lockFirst) {
    $h = $this->h;
    $attempt = $h->start();
    $h->fill($attempt, 2);
    $admin = taiKhoan('admin-p4@edunest.test', RoleCode::QuanTri);
    $lock = ['lock', ['actor' => $admin->id, 'target' => $h->a->id]];
    $submit = ['submit', ['actor' => $h->a->id, 'attempt' => $attempt->id]];
    if ($lockFirst) {
        giaoDichP4($lock, 'lock-account.locked', $submit);
    } else {
        giaoDichP4($submit, 'quiz-attempt.locked', $lock);
    }
    expect($attempt->refresh()->close_reason)->toBe($lockFirst ? LyDo::ThuHoi : LyDo::Nop)
        ->and($attempt->score_earned)->toBe('2.0000');
})->with([true, false]);

it('DT-09 Lưu thắng khóa tài khoản thì câu vừa lưu được chấm', function () {
    $h = $this->h;
    $attempt = $h->start();
    $question = $h->quiz->items()->firstOrFail();
    $admin = taiKhoan('admin-p4@edunest.test', RoleCode::QuanTri);
    giaoDichP4(['save', ['actor' => $h->a->id, 'attempt' => $attempt->id, 'question' => $question->id,
        'answer' => [$question->snapshot['options'][1]['id']]]], 'quiz-attempt.locked',
        ['lock', ['actor' => $admin->id, 'target' => $h->a->id]]);
    expect($attempt->refresh()->close_reason)->toBe(LyDo::ThuHoi)->and($attempt->score_earned)->toBe('1.0000');
});

it('AC-09-11 Hai yêu cầu hoàn thành bài chỉ ghi một mốc', function () {
    $h = $this->h;
    $mark = ['mark', ['actor' => $h->a->id, 'enrollment' => $h->enrollment->id, 'lesson' => $h->read->id]];
    [$a, $b] = giaoDichP4($mark, 'mark-lesson-completed.locked', $mark);
    expect($a['value'])->toBe($b['value'])->and($h->enrollment->lessonProgress()->count())->toBe(1);
});

it('AC-11-04 Hai tiến trình hoàn thành dùng cùng chứng nhận', function () {
    $h = $this->h;
    $h->mark($h->read);
    $h->mark($h->video);
    $h->quizLesson->update(['is_required' => false, 'is_final_test' => false]);
    $complete = ['complete', ['enrollment' => $h->enrollment->id]];
    [$a, $b] = giaoDichP4($complete, 'complete-enrollment.locked', $complete);
    expect($a['value'])->toBe($b['value'])->and($h->enrollment->certificate()->count())->toBe(1);
});

it('DT-13 Hoàn thành bài đọc và chốt quiz đạt chỉ cấp một chứng nhận', function () {
    $h = $this->h;
    $h->mark($h->video);
    $attempt = $h->start();
    $h->fill($attempt, 3);
    giaoDichP4(['mark', ['actor' => $h->a->id, 'enrollment' => $h->enrollment->id, 'lesson' => $h->read->id]],
        'mark-lesson-completed.locked', ['submit', ['actor' => $h->a->id, 'attempt' => $attempt->id]]);
    expect($h->enrollment->refresh()->learning_status)->toBe(LearningStatus::HoanThanh)
        ->and($h->enrollment->certificate()->sole()->completion_event_id)->toBe('HT-'.$h->enrollment->id);
});

it('DT-24 Quiz chờ ghi bài bắt buộc trước mở đúng sau commit', function () {
    $h = $this->h;
    $h->course->update(['learning_mode' => LearningMode::TuanTu]);
    $h->mark($h->video);
    [, $b] = giaoDichP4(['mark', ['actor' => $h->a->id, 'enrollment' => $h->enrollment->id, 'lesson' => $h->read->id]],
        'mark-lesson-completed.locked', ['start', ['actor' => $h->a->id, 'enrollment' => $h->enrollment->id, 'quiz' => $h->quiz->id]]);
    expect($b['value'])->toBe($h->enrollment->quizAttempts()->sole()->id);
});

it('DT-27 Đổi người soạn và cấp thêm lượt kiểm lại phạm vi dưới khóa', function (bool $grantFirst) {
    $h = $this->h;
    $h->quiz->update(['max_attempts' => 1]);
    $h->submit();
    $new = taiKhoan('moi-p4@edunest.test', RoleCode::GiaoVien);
    $grant = ['grant', ['actor' => $h->t->id, 'enrollment' => $h->enrollment->id, 'quiz' => $h->quiz->id]];
    $reassign = ['reassign', ['actor' => $h->g->id, 'course' => $h->course->id, 'new_author' => $new->id]];
    if ($grantFirst) {
        giaoDichP4($grant, 'grant-extra-quiz-attempt.locked', $reassign);
    } else {
        [, $b] = giaoDichP4($reassign, 'reassign-course-author.locked', $grant);
        expect($b['status'])->toBe(403);
    }
    expect(QuizAttemptGrant::query()->count())->toBe((int) $grantFirst)
        ->and(AuditLog::query()->where('action', 'QUIZ_EXTRA_ATTEMPT_GRANT')->count())->toBe((int) $grantFirst);
})->with([true, false]);

it('DT-30 Hai tác vụ phục hồi giữ một vé PDF và một dấu phát', function () {
    $h = $this->h;
    $certificate = $h->enrollment->certificate()->create([
        'certificate_number' => '2026-7KQ4M9XB', 'holder_name' => $h->a->name, 'course_title' => $h->course->title,
        'issued_on' => '2026-11-18', 'completion_event_id' => 'HT-'.$h->enrollment->id,
    ]);
    $recover = ['recover', ['certificate' => $certificate->id]];
    giaoDichP4($recover, 'completion.before-publish', $recover, blocks: false);
    expect(DB::table('jobs')->count())->toBe(1)->and($certificate->refresh()->completion_event_published_at)->not->toBeNull();
});

it('DT-31 Tiến trình thu hồi thắng worker PDF đang render', function () {
    $h = $this->h;
    $token = (string) Str::uuid();
    $certificate = $h->enrollment->certificate()->create([
        'certificate_number' => '2026-7KQ4M9XB', 'holder_name' => $h->a->name, 'course_title' => $h->course->title,
        'issued_on' => '2026-11-18', 'completion_event_id' => 'HT-'.$h->enrollment->id, 'pdf_job_token' => $token,
    ]);
    giaoDichP4(['pdf', ['certificate' => $certificate->id, 'token' => $token, 'disk' => Storage::disk('local')->path('')]],
        'certificate-pdf.rendered', ['revoke', ['actor' => $h->g->id, 'certificate' => $certificate->id]], blocks: false);
    expect($certificate->refresh()->status)->toBe(CertificateStatus::DaThuHoi)->and($certificate->pdf_path)->toBeNull();
    expect(Storage::disk('local')->allFiles('certificates'))->toBe([]);
});

it('DT-35 Hai worker khác vé, thất bại của vé cũ không chặn công bố của vé mới', function () {
    $h = $this->h;
    $token = (string) Str::uuid();
    $certificate = $h->enrollment->certificate()->create([
        'certificate_number' => '2026-7KQ4M9XB', 'holder_name' => $h->a->name, 'course_title' => $h->course->title,
        'issued_on' => '2026-11-18', 'completion_event_id' => 'HT-'.$h->enrollment->id, 'pdf_job_token' => $token,
    ]);
    giaoDichP4(['pdf', ['certificate' => $certificate->id, 'token' => $token, 'disk' => Storage::disk('local')->path('')]],
        'certificate-pdf.rendered', ['pdf-failed', ['certificate' => $certificate->id, 'token' => (string) Str::uuid()]], blocks: false);
    expect($certificate->refresh()->pdf_status)->toBe(CertificatePdfStatus::DaTao)
        ->and($certificate->pdf_job_token)->toBeNull()
        ->and(Storage::disk('local')->allFiles('certificates'))->toBe([$certificate->pdf_path]);
});

it('DT-36 Mốc nhận sớm lấy khóa muộn bị bỏ', function () {
    $h = $this->h;
    $shared = ['actor' => $h->a->id, 'enrollment' => $h->enrollment->id, 'lesson' => $h->video->id];
    [, $b] = giaoDichP4(['beat', $shared + ['position' => 20000, 'received' => HocTap::at('09:59:59')->toISOString()]],
        'record-watch-beat.locked', ['beat', $shared + ['position' => 10000, 'received' => HocTap::at('09:59:50')->toISOString()]]);
    expect($b['value'])->toBeFalse()->and($h->enrollment->lessonProgress()->sole()->last_position_ms)->toBe(20000);
});
