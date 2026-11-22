<?php

namespace App\Actions\M20;

use App\Actions\M18\ExamEvents;
use App\Actions\M21\XpLedger;
use App\Enums\M01\RoleCode;
use App\Enums\M04\QuestionType;
use App\Enums\M08\EnrollmentValidity;
use App\Enums\M20\DrawMode;
use App\Enums\M20\PracticeMode;
use App\Enums\M20\QuestionResult;
use App\Enums\M20\SessionEndReason;
use App\Enums\M20\SessionStatus;
use App\Enums\M21\XpSourceType;
use App\Models\M04\Question;
use App\Models\M04\QuizQuestion;
use App\Models\M08\Enrollment;
use App\Models\M20\PracticeActivity;
use App\Models\M20\PracticeQuestion;
use App\Models\M20\PracticeSession;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M10\QuizScore;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * QT-22 phiên luyện tập: bắt đầu (A4, X4), trả lời/bỏ qua/gợi ý (Learning 2 lần + 1 gợi ý, Try Hard 3 mạng + hạn câu),
 * chốt HOAN_THANH/HET_MANG/BO_DO và giao nguồn cho QT-23 trong cùng lần ghi chốt (BR-HT-02, CR-151..155).
 * Thứ tự khóa: `users` → `enrollments` → phiên (02d §4).
 */
final class PracticeSessions
{
    public const LIVES = 3;

    public const MAX_TRIES = 2;

    /** @var list<string> */
    public const TYPES = ['MOT_DAP_AN', 'NHIEU_DAP_AN', 'DUNG_SAI', 'DIEN_TU', 'SAP_XEP'];

    public function __construct(private XpLedger $ledger) {}

    public static function idleMinutes(): int
    {
        return (int) config('edunest.practice_idle_minutes', 60);
    }

    public function start(User $user, PracticeActivity $activity, PracticeMode $mode, ?CarbonImmutable $at = null): PracticeSession
    {
        $at ??= now()->floorMillisecond();

        return Tx::run(function () use ($user, $activity, $mode, $at) {
            $student = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            self::ensureStudent($student);
            $activity = PracticeActivity::query()->whereKey($activity->id)->firstOrFail();
            if ($activity->archived_at !== null) {
                throw new DomainRejection('Hoạt động này đã ngừng.');
            }
            $enrollment = Enrollment::query()->where('user_id', $student->id)->where('course_id', $activity->course_id)->lockForUpdate()->first();
            if ($enrollment === null) {
                throw DomainRejection::forbidden('Bạn chưa ghi danh khóa của hoạt động này.');
            }
            $validity = EnrollmentValidityChecker::for($enrollment, $at);
            if ($validity === EnrollmentValidity::DaThuHoi) {
                throw DomainRejection::forbidden('Quyền học của khóa đã bị thu hồi.');
            }
            if ($validity !== EnrollmentValidity::ConHan) {
                throw new DomainRejection('Ghi danh đã hết hạn, không bắt đầu phiên mới được (E16).');
            }
            // E38 + X4: rút trước khi chốt phiên cũ; tập rỗng thì giữ nguyên phiên cũ.
            $questions = $this->draw($activity, $student->id);
            if ($questions === []) {
                throw new DomainRejection('Hoạt động chưa có câu hỏi khả dụng.');
            }
            $key = $activity->id.':'.$student->id;
            $old = PracticeSession::query()->where('open_key', $key)->lockForUpdate()->first();
            if ($old !== null) {
                $this->close($old, SessionStatus::BoDo, SessionEndReason::PhienMoi, $at);
            }
            $tryHard = $mode === PracticeMode::TryHard;
            $session = PracticeSession::query()->create([
                'activity_id' => $activity->id, 'enrollment_id' => $enrollment->id, 'user_id' => $student->id, 'course_id' => $activity->course_id,
                'mode' => $mode, 'status' => SessionStatus::DangLam, 'level_factor' => number_format(XpLedger::currentFactorPct($student->id) / 100, 2, '.', ''),
                'lives' => $tryHard ? self::LIVES : null, 'question_count' => count($questions), 'current_position' => 1,
                'seconds_per_question' => $tryHard ? $activity->seconds_per_question : null,
                'question_deadline_at' => $tryHard ? $at->addSeconds($activity->seconds_per_question) : null,
                'last_activity_at' => $at, 'started_at' => $at, 'open_key' => $key,
            ]);
            foreach ($questions as $i => $question) {
                PracticeQuestion::query()->create([
                    'activity_session_id' => $session->id, 'position' => $i + 1, 'question_id' => $question->id, 'snapshot' => self::snapshot($question),
                    'opened_at' => $i === 0 ? $at : null, 'deadline_at' => $i === 0 ? $session->question_deadline_at : null,
                ]);
            }

            return $session;
        });
    }

    /**
     * Ghi một lần trả lời (null = bỏ qua, A10). Gửi lặp cùng mã yêu cầu trả lại kết quả đã ghi (X3).
     */
    public function answer(User $user, PracticeSession $session, int $position, mixed $value, string $requestId, ?CarbonImmutable $at = null): PracticeSession
    {
        $at ??= now()->floorMillisecond();
        [$session, $error] = Tx::run(function () use ($user, $session, $position, $value, $requestId, $at) {
            $session = $this->lockOwned($user, $session);
            $question = PracticeQuestion::query()->where('activity_session_id', $session->id)->where('position', $position)->lockForUpdate()->first();
            if ($question === null) {
                return [$session, 'Câu không thuộc phiên.'];
            }
            if (in_array($requestId, $question->request_keys ?? [], true)) {
                return [$session, null];
            }
            if ($error = $this->guard($session, $at)) {
                return [$session, $error];
            }
            if ($session->current_position !== $position || $question->result !== null) {
                return [$session, 'Câu này đã xử lý xong.'];
            }
            $grader = self::grader($question);
            $given = QuizScore::answer($grader, $value);
            $correct = $given !== null && QuizScore::correct($grader, $value);
            $question->tries++;
            $question->request_keys = [...($question->request_keys ?? []), $requestId];
            $question->answer = $given;
            $session->last_activity_at = $at;
            $done = $correct || $session->mode === PracticeMode::TryHard || $question->tries >= self::MAX_TRIES;
            if ($done) {
                $question->result = $correct ? QuestionResult::Dung : ($given === null ? QuestionResult::BoTrong : QuestionResult::Sai);
                $question->correct_on_try = $correct ? $question->tries : null;
                $question->finished_at = $at;
                if (! $correct && $session->mode === PracticeMode::TryHard) {
                    $session->lives = max(0, (int) $session->lives - 1);
                }
            }
            $question->save();
            $done ? $this->advance($session, $at) : $session->save();

            return [$session, null];
        });
        if ($error !== null) {
            throw new DomainRejection($error);
        }

        return $session;
    }

    /** A5: một gợi ý mỗi câu ở Learning; Try Hard không có gợi ý (BR-XP-09/11). */
    public function hint(User $user, PracticeSession $session, ?CarbonImmutable $at = null): PracticeSession
    {
        $at ??= now()->floorMillisecond();
        [$session, $error] = Tx::run(function () use ($user, $session, $at) {
            $session = $this->lockOwned($user, $session);
            if ($error = $this->guard($session, $at)) {
                return [$session, $error];
            }
            if ($session->mode !== PracticeMode::Learning) {
                return [$session, 'Try Hard không có gợi ý.'];
            }
            $question = PracticeQuestion::query()->where('activity_session_id', $session->id)->where('position', $session->current_position)->lockForUpdate()->firstOrFail();
            if ($question->hint_used) {
                return [$session, 'Mỗi câu chỉ dùng một gợi ý.'];
            }
            if (trim((string) ($question->snapshot['hint'] ?? '')) === '') {
                return [$session, 'Câu này không có gợi ý.'];
            }
            $question->forceFill(['hint_used' => true])->save();
            $session->forceFill(['last_activity_at' => $at])->save();

            return [$session, null];
        });
        if ($error !== null) {
            throw new DomainRejection($error);
        }

        return $session;
    }

    /** A9: tải lại chỉ chốt lười (quá ngưỡng, hạn câu), không đổi mốc hoạt động. */
    public function refresh(User $user, PracticeSession $session, ?CarbonImmutable $at = null): PracticeSession
    {
        $at ??= now()->floorMillisecond();
        if ($session->status !== SessionStatus::DangLam) {
            return $session;
        }

        return Tx::run(function () use ($user, $session, $at) {
            $session = $this->lockOwned($user, $session);
            $this->guard($session, $at);

            return $session;
        });
    }

    /** TV-14: chốt hạn câu Try Hard và phiên quá ngưỡng không hoạt động. Trả số phiên đã xét. */
    public function sweep(?CarbonImmutable $at = null): int
    {
        $at ??= now()->floorMillisecond();
        $ids = PracticeSession::query()->where('status', SessionStatus::DangLam)
            ->where(fn ($q) => $q->where('last_activity_at', '<', $at->subMinutes(self::idleMinutes()))->orWhere('question_deadline_at', '<', $at))
            ->limit(500)->get(['id', 'enrollment_id']);
        foreach ($ids as $row) {
            [$id, $enrollmentId] = [$row->id, $row->enrollment_id];
            Tx::run(function () use ($enrollmentId, $id, $at): void {
                Enrollment::query()->whereKey($enrollmentId)->lockForUpdate()->first();
                $session = PracticeSession::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                $this->guard($session, $at);
            });
        }

        return $ids->count();
    }

    /** E22: gọi trong transaction đang giữ khóa ghi danh của thu hồi. */
    public function closeForEnrollment(Enrollment $enrollment, CarbonImmutable $at): void
    {
        foreach (PracticeSession::query()->where('enrollment_id', $enrollment->id)->where('status', SessionStatus::DangLam)->lockForUpdate()->get() as $session) {
            $this->close($session, SessionStatus::BoDo, SessionEndReason::ThuHoi, $at);
        }
    }

    /** X5: gọi trong transaction đang giữ khóa `users` của khóa tài khoản. */
    public function closeForUser(User $user, CarbonImmutable $at): void
    {
        foreach (PracticeSession::query()->where('user_id', $user->id)->where('status', SessionStatus::DangLam)->lockForUpdate()->get() as $session) {
            $this->close($session, SessionStatus::BoDo, SessionEndReason::KhoaTaiKhoan, $at);
        }
    }

    /**
     * Câu khả dụng: DANG_DUNG, ngân hàng thường của đúng khóa (BR-XP-01, E38), N câu (A3) theo kiểu rút.
     *
     * @return list<Question>
     */
    public function draw(PracticeActivity $activity, int $userId): array
    {
        $bankIds = DB::table('activity_question_bank')->join('question_banks', 'question_banks.id', '=', 'activity_question_bank.question_bank_id')
            ->where('activity_question_bank.activity_id', $activity->id)->where('question_banks.course_id', $activity->course_id)
            ->where('question_banks.is_confidential', false)->pluck('question_banks.id');
        $base = Question::query()->whereIn('question_bank_id', $bankIds)->where('status', 'DANG_DUNG')->whereIn('type', self::TYPES);
        $n = $activity->question_count;
        $picked = new Collection;
        if ($activity->draw_mode === DrawMode::DiemYeu) {
            // CR-153: 10 phiên đã chốt gần nhất của khóa, ưu tiên câu sai ≥ 2 lần, hòa thì ngẫu nhiên.
            $recent = PracticeSession::query()->where('user_id', $userId)->where('course_id', $activity->course_id)
                ->where('status', '!=', SessionStatus::DangLam)->latest('ended_at')->latest('id')->limit(10)->pluck('id');
            $weak = PracticeQuestion::query()->whereIn('activity_session_id', $recent)
                ->whereIn('result', [QuestionResult::Sai->value, QuestionResult::BoTrong->value])
                ->groupBy('question_id')->havingRaw('COUNT(*) >= 2')->pluck('question_id');
            $picked = (clone $base)->whereIn('id', $weak)->inRandomOrder()->limit($n)->get();
        }
        if ($picked->count() < $n) {
            $picked = $picked->merge((clone $base)->whereNotIn('id', $picked->modelKeys())->inRandomOrder()->limit($n - $picked->count())->get());
        }

        return array_values($picked->load('options')->all());
    }

    /** @return array<string, mixed> */
    public static function snapshot(Question $question): array
    {
        $snapshot = ExamEvents::snapshot($question) + ['hint' => $question->hint];
        if ($question->type === QuestionType::SapXep) {
            $options = $snapshot['options'];
            shuffle($options);
            $snapshot['options'] = $options;
        }

        return $snapshot;
    }

    public static function grader(PracticeQuestion $question): QuizQuestion
    {
        return (new QuizQuestion)->forceFill(['snapshot' => $question->snapshot]);
    }

    private static function ensureStudent(User $user): void
    {
        if (! $user->isActive() || ! $user->hasEffectiveRole(RoleCode::HocVien)) {
            throw DomainRejection::forbidden('Chỉ học viên luyện tập được.');
        }
    }

    private function lockOwned(User $user, PracticeSession $session): PracticeSession
    {
        $student = User::query()->whereKey($user->id)->first();
        if ($student === null || $student->id !== $session->user_id) {
            throw DomainRejection::forbidden('Phiên không thuộc về bạn.');
        }
        self::ensureStudent($student);
        Enrollment::query()->whereKey($session->enrollment_id)->lockForUpdate()->first();

        return PracticeSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
    }

    /** Chốt lười dưới khóa: quá ngưỡng (X1) trước, rồi thu hồi (E22), rồi hạn câu Try Hard (A7). Trả lỗi nếu không còn ghi được. */
    private function guard(PracticeSession $session, CarbonImmutable $at): ?string
    {
        if ($session->status !== SessionStatus::DangLam) {
            return 'Phiên đã kết thúc.';
        }
        if ($at->gt($session->last_activity_at->addMinutes(self::idleMinutes()))) {
            $this->close($session, SessionStatus::BoDo, SessionEndReason::KhongHoatDong, $at);

            return 'Phiên đã bỏ dở vì quá '.self::idleMinutes().' phút không hoạt động.';
        }
        $enrollment = Enrollment::query()->whereKey($session->enrollment_id)->firstOrFail();
        if ($enrollment->revoked_at !== null) {
            $this->close($session, SessionStatus::BoDo, SessionEndReason::ThuHoi, $at);

            return 'Quyền học của khóa đã bị thu hồi.';
        }
        while ($session->status === SessionStatus::DangLam && $session->question_deadline_at !== null && $at->gt($session->question_deadline_at)) {
            $question = PracticeQuestion::query()->where('activity_session_id', $session->id)->where('position', $session->current_position)->lockForUpdate()->firstOrFail();
            $question->forceFill(['result' => QuestionResult::HetGio, 'finished_at' => $session->question_deadline_at])->save();
            $this->advance($session, $session->question_deadline_at);
        }

        return $session->status === SessionStatus::DangLam ? null : 'Phiên đã kết thúc.';
    }

    /** Bước 7: xét mạng trước, rồi mở câu sau hoặc chốt trong cùng lần ghi. */
    private function advance(PracticeSession $session, CarbonImmutable $at): void
    {
        if ($session->mode === PracticeMode::TryHard && (int) $session->lives <= 0) {
            $this->close($session, SessionStatus::HetMang, SessionEndReason::HetMang, $at);

            return;
        }
        if ($session->current_position >= $session->question_count) {
            $this->close($session, SessionStatus::HoanThanh, SessionEndReason::HoanThanh, $at);

            return;
        }
        $session->current_position++;
        $deadline = $session->mode === PracticeMode::TryHard ? $at->addSeconds((int) $session->seconds_per_question) : null;
        $session->question_deadline_at = $deadline;
        $session->save();
        PracticeQuestion::query()->where('activity_session_id', $session->id)->where('position', $session->current_position)
            ->update(['opened_at' => $at->format('Y-m-d H:i:s.v'), 'deadline_at' => $deadline?->format('Y-m-d H:i:s.v')]);
    }

    private function close(PracticeSession $session, SessionStatus $status, SessionEndReason $reason, CarbonImmutable $at): void
    {
        $session->forceFill(['status' => $status, 'end_reason' => $reason, 'ended_at' => $at, 'open_key' => null, 'question_deadline_at' => null])->save();
        if ($status === SessionStatus::BoDo) {
            return;
        }
        // Bước 8/A8: lưu yêu cầu xử lý bền vững cùng lần chốt (E40).
        $questions = PracticeQuestion::query()->where('activity_session_id', $session->id)->whereNotNull('result')->orderBy('position')->get();
        $this->ledger->record(XpSourceType::PhienLuyenTap, (string) $session->id, $session->user_id, $at, [
            'status' => $status->value, 'factor_pct' => (int) round((float) $session->level_factor * 100), 'question_count' => $session->question_count,
            'course_id' => $session->course_id, 'correct' => $questions->where('result', QuestionResult::Dung)->count(),
            'questions' => $questions->map(fn (PracticeQuestion $q) => ['position' => $q->position, 'difficulty' => $q->snapshot['difficulty'] ?? 'TRUNG_BINH',
                'correct_on_try' => $q->correct_on_try, 'hint_used' => $q->hint_used])->values()->all(),
        ]);
    }
}
