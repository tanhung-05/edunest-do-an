<?php

use App\Actions\M01\LockAccount;
use App\Actions\M01\UnlockAccount;
use App\Actions\M10\ContinueQuizAttempt;
use App\Actions\M10\GrantExtraQuizAttempt;
use App\Actions\M10\SaveAttemptAnswer;
use App\Actions\M10\SubmitQuizAttempt;
use App\Enums\M01\RoleCode;
use App\Enums\M03\LearningMode;
use App\Enums\M04\QuestionType;
use App\Enums\M05\CourseVersionStatus;
use App\Enums\M08\LearningStatus;
use App\Enums\M10\AttemptCloseReason as LyDo;
use App\Enums\M10\AttemptStatus as TrangThai;
use App\Models\M10\QuizAttempt;
use App\Models\M10\QuizAttemptGrant;
use App\Models\M11\AuditLog;
use App\Models\User;
use App\Policies\M10\QuizPolicy;
use App\Queries\M10\QuizAttempts;
use App\Support\DomainRejection;
use App\Support\M10\QuizScore;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;

pest()->group('P4');

beforeEach(function () {
    $this->travelTo(HocTap::at('09:00:00'));
    Storage::fake('local');
    $this->h = new HocTap;
    $this->travelTo(HocTap::at('10:00:00'));
});

function capP4(HocTap $h, ?User $actor = null, string $reason = 'Cho làm lại'): QuizAttemptGrant
{
    return app(GrantExtraQuizAttempt::class)->handle($actor ?? $h->t, $h->enrollment, $h->quiz, $reason);
}

function ketQuaP4(HocTap $h, QuizAttempt $attempt): array
{
    $h->signIn(test());

    return test()->get(route('student.attempts.result', $attempt))->assertOk()->viewData('page')['props'];
}

function nguongP4(HocTap $h, string $points, string $incorrect, bool $passed): void
{
    $questions = $h->quiz->items()->get();
    $questions[0]->update(['points' => $points]);
    $questions[1]->update(['points' => $incorrect]);
    $questions[2]->update(['points' => '0.01']);
    expect($h->submit(1)->passed)->toBe($passed);
}

it('AC-10-01 Qua tuyến học, bắt đầu, lưu, nộp và đọc kết quả', function () {
    $h = $this->h;
    $h->signIn($this);
    $target = $this->post($h->quizUrl('attempts.store'))->assertRedirect()->headers->get('Location');
    $props = $this->get($target)->assertOk()->viewData('page')['props'];
    foreach ($props['questions'] as $i => $q) {
        expect($q['choices'][0])->not->toHaveKey('is_correct');
        $this->patchJson($q['save_url'], ['answer' => $i < 3 ? [$q['choices'][1]['id']] : null])->assertOk();
    }
    $this->travelTo(HocTap::at('10:07:00'));
    $target = $this->post($props['submit_url'])->assertRedirect()->headers->get('Location');
    $this->get($target)->assertInertia(fn ($page) => $page->where('attempt.passed', true)->where('attempt.percent', '100.0'));
});

it('AC-10-02 Bấm bắt đầu lặp vẫn trả lượt và hạn ban đầu', function () {
    $h = $this->h;
    $h->submit();
    $h->submit();
    $attempt = $h->start();
    $this->travel(1)->minutes();
    $again = $h->start();
    expect($again->id)->toBe($attempt->id)->and($again->attempt_no)->toBe(3)
        ->and($again->deadline_at->equalTo(HocTap::at('10:10:00')))->toBeTrue();
});

it('AC-10-03 Mốc 69/100 trượt và 70/100 đạt', function () {
    nguongP4($this->h, '69', '30.99', false);
    nguongP4($this->h, '70', '29.99', true);
});

it('AC-10-04 Mốc 16/23 trượt và 17/23 đạt', function () {
    nguongP4($this->h, '16', '6.99', false);
    nguongP4($this->h, '17', '5.99', true);
});

it('AC-10-05 Tổng điểm theo trọng số từng câu', function () {
    $h = $this->h;
    $items = $h->quiz->items()->get();
    foreach ([1, 4, 2] as $i => $points) {
        $items[$i]->update(['points' => $points]);
    }
    $attempt = $h->start();
    foreach ([$items[0], $items[2]] as $item) {
        app(SaveAttemptAnswer::class)->handle($h->a, $attempt, $item, [$item->snapshot['options'][1]['id']]);
    }
    $result = app(SubmitQuizAttempt::class)->handle($h->a, $attempt);
    expect($result->score_earned)->toBe('3.0000')->and($result->score_max)->toBe('7.0000');
});

it('AC-10-06 Câu bỏ trống góp mẫu số và được chấm 0', function () {
    $result = $this->h->submit(2);
    expect($result->score_earned)->toBe('2.0000')->and($result->score_max)->toBe('3.0000');
});

it('AC-10-07 Nhiều đáp án chỉ đúng khi khớp toàn bộ tập lựa chọn', function () {
    $question = $this->h->quiz->items()->firstOrFail();
    $snapshot = $question->snapshot;
    $snapshot['type'] = QuestionType::NhieuDapAn->value;
    foreach ($snapshot['options'] as $i => &$choice) {
        $choice['is_correct'] = in_array($i, [0, 2], true);
    }
    unset($choice);
    $question->snapshot = $snapshot;
    [$a, $b, $c] = array_column($snapshot['options'], 'id');
    expect(QuizScore::correct($question, [$c, $a]))->toBeTrue()
        ->and(QuizScore::correct($question, [$a]))->toBeFalse()
        ->and(QuizScore::correct($question, [$a, $b, $c]))->toBeFalse();
});

it('AC-10-08 Điểm ghi nhận là điểm lớn nhất đã chốt', function () {
    $h = $this->h;
    $h->submit(2);
    $h->submit(1);
    expect(QuizScore::display(QuizAttempts::best($h->enrollment, $h->quiz)))->toBe('66.7');
});

it('AC-10-09 Chưa đạt và còn lượt thì ẩn đáp án, mở lượt kế', function () {
    $h = $this->h;
    expect(ketQuaP4($h, $h->submit(1))['answers_visible'])->toBeFalse();
    expect($h->start()->attempt_no)->toBe(2);
});

it('AC-10-10 Lượt sau thấp điểm không giảm điểm ghi nhận', function () {
    $h = $this->h;
    foreach ([1, 2, 0] as $count) {
        $last = $h->submit($count);
    }
    expect(ketQuaP4($h, $last)['best_percent'])->toBe('66.7');
});

it('AC-10-11 Hết lượt chưa đạt hiện đáp án nhưng vẫn khóa bài bắt buộc sau', function () {
    $h = $this->h;
    $h->course->update(['learning_mode' => LearningMode::TuanTu]);
    $h->mark($h->read);
    $h->mark($h->video);
    foreach ([0, 1, 2] as $count) {
        $last = $h->submit($count);
    }
    $props = ketQuaP4($h, $last);
    expect($props['answers_visible'])->toBeTrue()->and($props['next_lesson_url'])->toBeNull();
    $this->getJson($h->lessonUrl(lesson: $h->optional))->assertForbidden();
});

it('AC-10-12 Tự do mở bài sau dù hết lượt, ghi danh vẫn đang học', function () {
    $h = $this->h;
    $h->quiz->update(['max_attempts' => 1]);
    $attempt = $h->submit(1);
    expect(ketQuaP4($h, $attempt)['answers_visible'])->toBeTrue();
    $this->get($h->lessonUrl(lesson: $h->optional))->assertOk();
    expect($h->enrollment->refresh()->learning_status)->toBe(LearningStatus::DangHoc);
});

it('AC-10-13 Đã đạt thì không tạo lượt nữa', function () {
    $this->h->submit(3);
    expect(fn () => $this->h->start())->toThrow(DomainRejection::class, 'Bạn đã đạt quiz này.');
});

it('AC-10-14 Giáo viên cấp thêm đúng một lượt, có nhật ký', function () {
    $h = $this->h;
    $h->quiz->update(['max_attempts' => 1]);
    $h->submit();
    $this->signIn($h->t)->post(route('teacher.courses.enrollments.quizzes.extra-attempts.store',
        [$h->course, $h->enrollment, $h->quiz]), ['reason' => 'Cho làm lại sau khi ôn'])->assertRedirect();
    expect(QuizAttemptGrant::query()->sole()->extra_attempts)->toBe(1)
        ->and(AuditLog::query()->where('reason', 'Cho làm lại sau khi ôn')->count())->toBe(1)
        ->and($h->start()->attempt_no)->toBe(2);
});

it('AC-10-15 Lý do chỉ có khoảng trắng bị từ chối', function () {
    $h = $this->h;
    $this->signIn($h->t)->postJson(route('teacher.courses.enrollments.quizzes.extra-attempts.store',
        [$h->course, $h->enrollment, $h->quiz]), ['reason' => '   '])->assertUnprocessable()->assertJsonValidationErrors('reason');
    expect(QuizAttemptGrant::query()->count())->toBe(0);
});

it('AC-10-16 Còn lượt thì không cấp thêm', function () {
    $this->h->submit(1);
    expect(fn () => capP4($this->h))->toThrow(DomainRejection::class);
    expect(QuizAttemptGrant::query()->count())->toBe(0);
});

it('AC-10-17 Học viên không tự cấp lượt', function () {
    $h = $this->h;
    $this->signIn($h->a)->postJson(route('academic.enrollments.quizzes.extra-attempts.store', [$h->enrollment, $h->quiz]),
        ['reason' => 'Cấp thử'])->assertForbidden();
});

it('AC-10-18 Giáo viên ngoài phạm vi không cấp lượt', function () {
    $h = $this->h;
    $other = taiKhoan('ngoai-pham-vi-p4@edunest.test', RoleCode::GiaoVien);
    $this->signIn($other)->postJson(route('teacher.courses.enrollments.quizzes.extra-attempts.store',
        [$h->course, $h->enrollment, $h->quiz]), ['reason' => 'Cấp thử'])->assertForbidden();
});

it('AC-10-19 Tải lại khôi phục câu đã lưu và hạn ban đầu', function () {
    $h = $this->h;
    $attempt = $h->start();
    $h->fill($attempt, 1);
    $this->travelTo(HocTap::at('10:06:00'));
    $h->signIn($this)->get(route('student.attempts.show', $attempt))->assertInertia(fn ($page) => $page
        ->where('attempt.deadline_at', HocTap::at('10:10:00')->toISOString())->has('questions.0.answer', 1));
});

it('AC-10-20 Đổi thiết bị bằng phiên mới vẫn tiếp tục cùng lượt', function () {
    $h = $this->h;
    $attempt = $h->start();
    $h->fill($attempt, 1);
    $this->travelTo(HocTap::at('10:03:00'));
    $h->signIn($this, cookie: str_repeat('o', 64))->get(route('student.attempts.show', $attempt))
        ->assertInertia(fn ($page) => $page->where('attempt.id', $attempt->id)->has('questions.0.answer', 1));
});

it('AC-10-21 Lưu câu đúng hạn, bỏ câu đến sau hạn và vẫn chốt', function () {
    $h = $this->h;
    $attempt = $h->start();
    $h->signIn($this);
    foreach ($h->quiz->items()->get() as $i => $question) {
        $this->travelTo(HocTap::at('10:10:00')->addSeconds($i - 1));
        $response = $this->patchJson(route('student.attempts.answers.update', [$attempt, $question]),
            ['answer' => [$question->snapshot['options'][1]['id']]]);
        $response->assertStatus($i < 2 ? 200 : 422);
    }
    expect($attempt->refresh()->status)->toBe(TrangThai::HetGio)->and($attempt->score_earned)->toBe('2.0000');
});

it('AC-10-22 Nộp đúng hạn là NOP, sau hạn là HET_GIO', function () {
    $h = $this->h;
    foreach ([0 => LyDo::Nop, 1 => LyDo::HetGio] as $seconds => $reason) {
        $attempt = $h->start();
        $this->travelTo($attempt->deadline_at->addSeconds($seconds));
        $submit = fn () => app(SubmitQuizAttempt::class)->handle($h->a, $attempt);
        if ($seconds > 0) {
            expect($submit)->toThrow(DomainRejection::class, 'Lượt đã hết giờ.');
        } else {
            $submit();
        }
        expect($attempt->refresh()->close_reason)->toBe($reason);
    }
});

it('AC-10-23 Quét sau hạn chấm phần đã lưu', function () {
    $attempt = $this->h->start();
    $this->h->fill($attempt, 1);
    $this->travelTo(HocTap::at('10:11:00'));
    $this->artisan('learning:finalize-overdue-attempts')->assertSuccessful();
    expect($attempt->refresh()->score_earned)->toBe('1.0000')->and($attempt->close_reason)->toBe(LyDo::HetGio);
});

it('AC-10-24 Mở lại sau hạn chuyển thẳng sang kết quả', function () {
    $h = $this->h;
    $attempt = $h->start();
    $this->travelTo(HocTap::at('10:10:30'));
    $h->signIn($this)->get(route('student.attempts.show', $attempt))->assertRedirect(route('student.attempts.result', $attempt));
});

it('AC-10-25 Lệnh quét lặp giữ nguyên giờ chốt và điểm', function () {
    $h = $this->h;
    $attempt = $h->submit(1);
    $at = $attempt->finalized_at;
    $this->travel(1)->hours();
    $this->artisan('learning:finalize-overdue-attempts')->assertSuccessful();
    expect($attempt->refresh()->finalized_at->equalTo($at))->toBeTrue()->and($attempt->close_reason)->toBe(LyDo::Nop);
});

it('AC-10-27 Lưu vào lượt cũ đã chốt không ghi đè câu', function () {
    $h = $this->h;
    $old = $h->submit(1);
    $open = $h->start();
    $q = $h->quiz->items()->firstOrFail();
    $h->signIn($this)->patchJson(route('student.attempts.answers.update', [$old, $q]), ['answer' => null])->assertUnprocessable();
    $this->patchJson(route('student.attempts.answers.update', [$open, $q]), ['answer' => [$q->snapshot['options'][1]['id']]])->assertOk();
    expect($old->refresh()->score_earned)->toBe('1.0000');
});

it('AC-10-29 Chỉ bắt đầu trước hạn ghi danh', function () {
    $h = $this->h;
    $expiry = HocTap::at('10:00:00');
    $h->enrollment->update(['het_han_luc' => $expiry]);
    $this->travelTo($expiry->subSecond());
    $h->submit();
    $this->travelTo($expiry);
    expect(fn () => $h->start())->toThrow(DomainRejection::class, 'Đã hết hạn truy cập.');
});

it('AC-10-30 Lượt bắt đầu trước hạn vẫn đọc, lưu và nộp được', function () {
    $h = $this->h;
    $h->quiz->update(['duration_minutes' => 30]);
    $h->enrollment->update(['het_han_luc' => HocTap::at('10:01:00')]);
    $attempt = $h->start();
    $this->travelTo(HocTap::at('10:05:00'));
    expect(app(ContinueQuizAttempt::class)->handle($h->a, $attempt)->status)->toBe(TrangThai::DangLam);
    $this->travelTo(HocTap::at('10:15:00'));
    $h->fill($attempt, 2);
    $this->travelTo(HocTap::at('10:19:00'));
    expect(app(SubmitQuizAttempt::class)->handle($h->a, $attempt)->close_reason)->toBe(LyDo::Nop);
});

it('AC-10-31 Quá hạn lượt sau khi hết hạn ghi danh thì chốt và từ chối lưu', function () {
    $h = $this->h;
    $h->enrollment->update(['het_han_luc' => HocTap::at('10:01:00')]);
    $attempt = $h->start();
    $this->travelTo(HocTap::at('10:10:01'));
    expect(fn () => $h->fill($attempt, 1))->toThrow(DomainRejection::class);
    expect($attempt->refresh()->close_reason)->toBe(LyDo::HetGio);
});

it('AC-10-32 Đạt khi làm nốt sau hết hạn vẫn hoàn thành bài', function () {
    $h = $this->h;
    $h->enrollment->update(['het_han_luc' => HocTap::at('10:01:00')]);
    $attempt = $h->start();
    $this->travelTo(HocTap::at('10:05:00'));
    $h->fill($attempt, 3);
    expect(app(SubmitQuizAttempt::class)->handle($h->a, $attempt)->passed)->toBeTrue();
    expect($h->enrollment->lessonProgress()->where('lesson_id', $h->quizLesson->id)->sole()->completed_at)->not->toBeNull();
});

it('AC-10-34 Khóa tài khoản chốt lượt và giữ điểm đã lưu', function () {
    $h = $this->h;
    $attempt = $h->start();
    $h->fill($attempt, 2);
    $admin = taiKhoan('quantri-p4@edunest.test', RoleCode::QuanTri);
    $this->travelTo(HocTap::at('10:05:00'));
    app(LockAccount::class)->handle($admin, $h->a);
    expect($attempt->refresh()->close_reason)->toBe(LyDo::ThuHoi)->and($attempt->score_earned)->toBe('2.0000');
});

it('AC-10-35 Học tuần tự chưa xong bài trước thì không bắt đầu quiz', function () {
    $this->h->course->update(['learning_mode' => LearningMode::TuanTu]);
    expect(fn () => $this->h->start())->toThrow(DomainRejection::class, 'Hoàn thành bài bắt buộc trước');
});

it('AC-10-36 Đổi đáp án ngân hàng không đổi bản chụp đã công bố', function () {
    $h = $this->h;
    $question = $h->quiz->items()->firstOrFail();
    $question->question()->firstOrFail()->options()->update(['is_correct' => false]);
    expect($h->submit(3)->passed)->toBeTrue();
});

it('AC-10-37 Hết hạn còn lượt vẫn xem điểm cũ và ẩn đáp án', function () {
    $h = $this->h;
    $attempt = $h->submit(1);
    $h->enrollment->update(['het_han_luc' => now()]);
    $props = ketQuaP4($h, $attempt);
    expect($props['answers_visible'])->toBeFalse()->and($props['can_start'])->toBeFalse()->and($props['attempt']['percent'])->toBe('33.3');
});

it('AC-10-38 Giáo vụ không bắt đầu lượt học viên', function () {
    $this->signIn($this->h->g)->postJson($this->h->quizUrl('attempts.store'))->assertForbidden();
});

it('AC-10-39 Học viên khác không lưu câu hoặc bắt đầu nếu không ghi danh', function () {
    $h = $this->h;
    $attempt = $h->start();
    $q = $h->quiz->items()->firstOrFail();
    $h->signIn($this, HocTap::student('nguoi-khac'))->patchJson(route('student.attempts.answers.update', [$attempt, $q]), ['answer' => null])->assertForbidden();
    $this->postJson($h->quizUrl('attempts.store'))->assertForbidden();
});

it('AC-10-40 Khách phải đăng nhập khi truy cập kết quả', function () {
    $this->get(route('student.attempts.result', $this->h->submit()))->assertRedirect(route('login'));
});

it('AC-10-41 Học viên khác không đọc được kết quả', function () {
    $h = $this->h;
    $h->signIn($this, HocTap::student('khac'))->getJson(route('student.attempts.result', $h->submit()))->assertForbidden();
});

it('AC-10-42 Giáo vụ cấp thêm lượt theo đúng ghi danh', function () {
    $h = $this->h;
    $h->quiz->update(['max_attempts' => 1]);
    $h->submit();
    $this->signIn($h->g)->post(route('academic.enrollments.quizzes.extra-attempts.store',
        [$h->enrollment, $h->quiz]), ['reason' => 'Lỗi đường truyền'])->assertRedirect();
    expect($h->start()->attempt_no)->toBe(2);
});

it('AC-10-43 Quiz không bắt buộc không được cấp thêm lượt', function () {
    $h = $this->h;
    $h->quiz->update(['max_attempts' => 1]);
    $h->quizLesson->update(['is_required' => false, 'is_final_test' => false]);
    $h->submit();
    expect(fn () => capP4($h))->toThrow(DomainRejection::class);
});

it('AC-10-44 Quiz đã đạt không cấp thêm lượt', function () {
    $h = $this->h;
    $h->quiz->update(['max_attempts' => 1]);
    $h->submit(3);
    expect(fn () => capP4($h))->toThrow(DomainRejection::class);
});

it('AC-10-45 Lượt cuối chưa chốt không mở quyền xem đáp án', function () {
    $h = $this->h;
    $first = $h->submit(1);
    $h->submit();
    $h->start();
    expect(app(QuizPolicy::class)->viewAnswers($h->a, $h->quiz, $first))->toBeFalse();
});

it('AC-10-46 Đang làm lượt cuối thì chưa đủ điều kiện cấp', function () {
    $h = $this->h;
    $h->quiz->update(['max_attempts' => 1]);
    $h->start();
    expect(fn () => capP4($h))->toThrow(DomainRejection::class);
    expect(fn () => capP4($h, $h->g))->toThrow(DomainRejection::class);
});

it('AC-10-47 Giáo vụ không được lưu hoặc nộp lượt của học viên', function () {
    $h = $this->h;
    $attempt = $h->start();
    $q = $h->quiz->items()->firstOrFail();
    $this->signIn($h->g)->patchJson(route('student.attempts.answers.update', [$attempt, $q]), ['answer' => null])->assertForbidden();
    $this->postJson(route('student.attempts.submit', $attempt))->assertForbidden();
});

it('AC-10-48 Học viên khác không nộp được lượt', function () {
    $h = $this->h;
    $h->signIn($this, HocTap::student('nguoi-khac'))->postJson(route('student.attempts.submit', $h->start()))->assertForbidden();
});

it('AC-10-49 69,96% hiển thị 70,0 nhưng chưa đạt, 70% thì đạt', function () {
    nguongP4($this->h, '69.96', '30.03', false);
    $attempt = $this->h->enrollment->quizAttempts()->sole();
    expect(QuizScore::display(QuizScore::ratio($attempt)))->toBe('70.0');
    nguongP4($this->h, '70', '29.99', true);
});

it('AC-10-51 Khóa tài khoản sau khi đã lưu đủ vẫn ghi tiến độ đạt', function () {
    $h = $this->h;
    $attempt = $h->start();
    $h->fill($attempt, 3);
    $admin = taiKhoan('quantri-p4@edunest.test', RoleCode::QuanTri);
    $this->travelTo(HocTap::at('10:05:00'));
    app(LockAccount::class)->handle($admin, $h->a);
    expect($attempt->refresh()->passed)->toBeTrue()->and($attempt->close_reason)->toBe(LyDo::ThuHoi)
        ->and($h->enrollment->lessonProgress()->whereNotNull('completed_at')->where('lesson_id', $h->quizLesson->id)->exists())->toBeTrue();
});

it('AC-10-53 Dùng hết lượt cấp thêm mới được cấp tiếp', function () {
    $h = $this->h;
    $h->quiz->update(['max_attempts' => 1]);
    $h->submit();
    capP4($h, $h->g);
    expect(fn () => capP4($h, $h->g))->toThrow(DomainRejection::class);
    $h->submit();
    capP4($h, $h->g, 'Lỗi đường truyền');
    expect(QuizAttemptGrant::query()->count())->toBe(2)->and($h->start()->attempt_no)->toBe(3);
});

it('AC-10-56 Học viên sai phạm vi bị chặn kết quả và lịch sử', function () {
    $h = $this->h;
    $attempt = $h->submit(3);
    $h->signIn($this, HocTap::student('khac'))->getJson(route('student.attempts.result', $attempt))->assertForbidden();
    $this->getJson($h->quizUrl('history'))->assertForbidden();
});

it('AC-10-56 Giáo viên không được giao khóa bị chặn kết quả lượt; người soạn của khóa thì xem được', function () {
    $h = $this->h;
    $attempt = $h->submit(3);
    $url = route('teacher.courses.attempts.show', ['course' => $h->course->id, 'attempt' => $attempt->id]);
    $other = taiKhoan('giaovien-khac-p4@edunest.test', RoleCode::GiaoVien);
    $this->signIn($other)->get($url)->assertForbidden();
    $this->signIn($h->t)->get($url)->assertOk()
        ->assertInertia(fn ($page) => $page->component('teacher/courses/attempt-result')
            ->where('attempt.attempt_no', $attempt->attempt_no)
            ->has('questions', 3));
});

it('AC-10-57 Giáo vụ không đọc bài làm và đáp án của học viên', function () {
    $h = $this->h;
    $attempt = $h->submit(3);
    $this->signIn($h->g)->getJson($h->quizUrl('history'))->assertForbidden();
    $this->getJson(route('student.attempts.result', $attempt))->assertForbidden();
});

it('AC-10-58 Mốc khóa tài khoản và hạn lượt quyết định lý do chốt', function () {
    $h = $this->h;
    $admin = taiKhoan('quantri-p4@edunest.test', RoleCode::QuanTri);
    $attempt = $h->start();
    $h->fill($attempt, 1);
    $this->travelTo(HocTap::at('10:05:00'));
    app(LockAccount::class)->handle($admin, $h->a);
    expect($attempt->refresh()->close_reason)->toBe(LyDo::ThuHoi);
    app(UnlockAccount::class)->handle($admin, $h->a);
    $h->a->refresh(); // Mở khóa xong học viên đăng nhập lại với thế hệ xác thực mới (CR-04).
    $other = $h->start();
    $h->fill($other, 1);
    $this->travelTo($other->deadline_at->addSecond());
    app(LockAccount::class)->handle($admin, $h->a);
    $this->artisan('learning:finalize-overdue-attempts')->assertSuccessful();
    expect($other->refresh()->close_reason)->toBe(LyDo::HetGio)->and($other->score_earned)->toBe('1.0000');
});

it('AC-10-33 Chốt khi thu hồi quyền học')->todo('P5: thao tác thu hồi quyền học');
it('AC-10-50 Giữ điểm khi thu hồi quyền học')->todo('P5: thao tác thu hồi quyền học');
it('AC-10-52 Khôi phục quyền ghi hoàn thành từ lượt đã đạt')->todo('P5: khôi phục quyền học');
it('AC-10-54 Thu hồi quyền trước hạn lượt')->todo('P5: thao tác thu hồi quyền học');
it('AC-10-55 Thu hồi quyền sau hạn lượt')->todo('P5: thao tác thu hồi quyền học');
it('AC-10-59 Hai thứ tự thu hồi quyền học và tác vụ quét')->todo('P5: thao tác thu hồi quyền học');
it('AC-10-60 Chốt khi chuyển phiên bản')->todo('P6: chuyển phiên bản');

it('AC-05-05 Chấm lượt bản cũ theo đáp án chụp kể cả sau khi công bố bản mới', function () {
    $h = $this->h;
    $attempt = $h->start();
    $h->version->update(['status' => CourseVersionStatus::DaThayThe]);
    $v2 = $h->course->versions()->create(['version_no' => 2, 'status' => CourseVersionStatus::DaCongBo, 'published_at' => now()]);
    $h->course->update(['current_version_id' => $v2->id]);
    $q = $h->quiz->items()->firstOrFail();
    $options = $q->question()->firstOrFail()->options()->get();
    $options[1]->update(['is_correct' => false]);
    $options[2]->update(['is_correct' => true]);
    $h->fill($attempt, 1);
    expect(app(SubmitQuizAttempt::class)->handle($h->a, $attempt)->score_earned)->toBe('1.0000');
});

it('AC-01-17 Khóa tài khoản trước hạn chấm phần đã lưu và giữ trạng thái ghi danh', function () {
    $h = $this->h;
    $this->travelTo(HocTap::at('09:55:00'));
    $attempt = $h->start();
    $h->fill($attempt, 2);
    $admin = taiKhoan('quantri-p4@edunest.test', RoleCode::QuanTri);
    $this->travelTo(HocTap::at('10:00:00'));
    $this->signIn($admin)->patch(route('admin.users.lock', $h->a), ['reason' => 'Vi phạm'])->assertRedirect();
    expect($attempt->refresh()->status)->toBe(TrangThai::DaNop)->and($attempt->close_reason)->toBe(LyDo::ThuHoi)
        ->and($attempt->score_earned)->toBe('2.0000')->and($h->enrollment->refresh()->learning_status)->toBe(LearningStatus::DangHoc);
});
