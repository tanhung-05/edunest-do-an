<?php

namespace App\Actions\M22;

use App\Actions\M20\PracticeSessions;
use App\Actions\M21\XpLedger;
use App\Enums\M01\RoleCode;
use App\Enums\M08\EnrollmentValidity;
use App\Enums\M20\SessionEndReason;
use App\Enums\M20\SessionStatus;
use App\Enums\M21\XpSourceType;
use App\Enums\M22\CardResult;
use App\Enums\M22\GameKind;
use App\Models\M08\Enrollment;
use App\Models\M22\Flashcard;
use App\Models\M22\FlashcardSet;
use App\Models\M22\GameCard;
use App\Models\M22\GameSession;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M10\QuizScore;
use App\Support\Tx;
use Carbon\CarbonImmutable;

/**
 * QT-24 bước 7–14: Bắn bong bóng và Mưa chữ. Mỗi thẻ một kết quả; chốt HOAN_THANH ở thẻ cuối và giao nguồn PHIEN_GAME
 * cho QT-23 cùng lần ghi (BR-XP-67/68). Game không đổi lịch ôn (BR-XP-65). Bỏ dở theo CR-151/155 như phiên luyện tập.
 */
final class GameSessions
{
    public const MAX_CARDS = 20;

    public function __construct(private XpLedger $ledger) {}

    public function start(User $user, FlashcardSet $set, GameKind $kind, ?CarbonImmutable $at = null): GameSession
    {
        $at ??= now()->floorMillisecond();

        return Tx::run(function () use ($user, $set, $kind, $at) {
            $student = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $student->isActive() || ! $student->hasEffectiveRole(RoleCode::HocVien)) {
                throw DomainRejection::forbidden('Chỉ học viên chơi được.');
            }
            $set = FlashcardSet::query()->whereKey($set->id)->whereNull('archived_at')->firstOrFail();
            $enrollment = FlashcardReviews::activeEnrollment($student, $set->course_id, $at, true);
            $cards = Flashcard::query()->where('flashcard_set_id', $set->id)->whereNull('archived_at')->inRandomOrder()->limit(self::MAX_CARDS)->get();
            if ($cards->count() < ($kind === GameKind::BanBong ? 2 : 1)) {
                throw new DomainRejection($kind === GameKind::BanBong ? 'Bắn bong bóng cần bộ thẻ có ít nhất 2 thẻ.' : 'Bộ thẻ chưa có thẻ.');
            }
            $key = $set->id.':'.$student->id;
            $old = GameSession::query()->where('open_key', $key)->lockForUpdate()->first();
            if ($old !== null) {
                $this->close($old, SessionStatus::BoDo, SessionEndReason::PhienMoi, $at);
            }
            $session = GameSession::query()->create([
                'flashcard_set_id' => $set->id, 'user_id' => $student->id, 'enrollment_id' => $enrollment->id, 'course_id' => $set->course_id, 'kind' => $kind,
                'status' => SessionStatus::DangLam, 'level_factor' => number_format(XpLedger::currentFactorPct($student->id) / 100, 2, '.', ''),
                'card_count' => $cards->count(), 'current_position' => 1, 'last_activity_at' => $at, 'started_at' => $at, 'open_key' => $key,
            ]);
            $backs = Flashcard::query()->where('flashcard_set_id', $set->id)->whereNull('archived_at')->pluck('back')->unique()->values()->all();
            foreach ($cards->values() as $i => $card) {
                $snapshot = ['front' => $card->front, 'back' => $card->back, 'image_path' => $card->image_path, 'speech_lang' => $card->speech_lang];
                if ($kind === GameKind::BanBong) {
                    $others = array_values(array_filter($backs, fn ($b) => $b !== $card->back));
                    shuffle($others);
                    $options = [...array_slice($others, 0, 3), $card->back];
                    shuffle($options);
                    $snapshot['options'] = $options;
                }
                GameCard::query()->create(['game_session_id' => $session->id, 'position' => $i + 1, 'flashcard_id' => $card->id, 'snapshot' => $snapshot]);
            }

            return $session;
        });
    }

    /** Ghi kết quả thẻ đang mở. `$value` null + `$bottom` = chữ chạm đáy ở Mưa chữ (BR-XP-69). */
    public function answer(User $user, GameSession $session, int $position, ?string $value, bool $bottom, string $requestId, ?CarbonImmutable $at = null): GameSession
    {
        $at ??= now()->floorMillisecond();
        [$session, $error] = Tx::run(function () use ($user, $session, $position, $value, $bottom, $requestId, $at) {
            $session = $this->lockOwned($user, $session);
            $card = GameCard::query()->where('game_session_id', $session->id)->where('position', $position)->lockForUpdate()->first();
            if ($card === null) {
                return [$session, 'Thẻ không thuộc phiên.'];
            }
            if ($card->request_id === $requestId) {
                return [$session, null];
            }
            if ($error = $this->guard($session, $at)) {
                return [$session, $error];
            }
            if ($session->current_position !== $position || $card->result !== null) {
                return [$session, 'Thẻ này đã có kết quả.'];
            }
            $back = (string) $card->snapshot['back'];
            $result = match (true) {
                $session->kind === GameKind::MuaChu && $bottom => CardResult::ChamDay,
                $value !== null && QuizScore::normalize($value) === QuizScore::normalize($back) => CardResult::Dung,
                default => CardResult::Sai,
            };
            $card->forceFill(['result' => $result, 'answer' => $value === null ? null : mb_substr($value, 0, 500), 'request_id' => $requestId, 'answered_at' => $at])->save();
            $session->last_activity_at = $at;
            if ($session->current_position >= $session->card_count) {
                $this->close($session, SessionStatus::HoanThanh, SessionEndReason::HoanThanh, $at);
            } else {
                $session->current_position++;
                $session->save();
            }

            return [$session, null];
        });
        if ($error !== null) {
            throw new DomainRejection($error);
        }

        return $session;
    }

    public function refresh(User $user, GameSession $session, ?CarbonImmutable $at = null): GameSession
    {
        if ($session->status !== SessionStatus::DangLam) {
            return $session;
        }

        return Tx::run(function () use ($user, $session, $at) {
            $session = $this->lockOwned($user, $session);
            $this->guard($session, $at ?? now()->floorMillisecond());

            return $session;
        });
    }

    public function sweep(?CarbonImmutable $at = null): int
    {
        $at ??= now()->floorMillisecond();
        $rows = GameSession::query()->where('status', SessionStatus::DangLam)
            ->where('last_activity_at', '<', $at->subMinutes(PracticeSessions::idleMinutes()))->limit(500)->get(['id', 'enrollment_id']);
        foreach ($rows as $row) {
            Tx::run(function () use ($row, $at): void {
                Enrollment::query()->whereKey($row->enrollment_id)->lockForUpdate()->first();
                $session = GameSession::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
                $this->guard($session, $at);
            });
        }

        return $rows->count();
    }

    public function closeForEnrollment(Enrollment $enrollment, CarbonImmutable $at): void
    {
        foreach (GameSession::query()->where('enrollment_id', $enrollment->id)->where('status', SessionStatus::DangLam)->lockForUpdate()->get() as $session) {
            $this->close($session, SessionStatus::BoDo, SessionEndReason::ThuHoi, $at);
        }
    }

    public function closeForUser(User $user, CarbonImmutable $at): void
    {
        foreach (GameSession::query()->where('user_id', $user->id)->where('status', SessionStatus::DangLam)->lockForUpdate()->get() as $session) {
            $this->close($session, SessionStatus::BoDo, SessionEndReason::KhoaTaiKhoan, $at);
        }
    }

    private function lockOwned(User $user, GameSession $session): GameSession
    {
        $student = User::query()->whereKey($user->id)->first();
        if ($student === null || $student->id !== $session->user_id || ! $student->isActive() || ! $student->hasEffectiveRole(RoleCode::HocVien)) {
            throw DomainRejection::forbidden('Phiên không thuộc về bạn.');
        }
        Enrollment::query()->whereKey($session->enrollment_id)->lockForUpdate()->first();

        return GameSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
    }

    private function guard(GameSession $session, CarbonImmutable $at): ?string
    {
        if ($session->status !== SessionStatus::DangLam) {
            return 'Phiên đã kết thúc.';
        }
        $idle = PracticeSessions::idleMinutes();
        if ($at->gt($session->last_activity_at->addMinutes($idle))) {
            $this->close($session, SessionStatus::BoDo, SessionEndReason::KhongHoatDong, $at);

            return 'Phiên đã bỏ dở vì quá '.$idle.' phút không hoạt động.';
        }
        $enrollment = Enrollment::query()->whereKey($session->enrollment_id)->firstOrFail();
        if (EnrollmentValidityChecker::for($enrollment, $at) === EnrollmentValidity::DaThuHoi) {
            $this->close($session, SessionStatus::BoDo, SessionEndReason::ThuHoi, $at);

            return 'Quyền học của khóa đã bị thu hồi.';
        }

        return null;
    }

    private function close(GameSession $session, SessionStatus $status, SessionEndReason $reason, CarbonImmutable $at): void
    {
        $session->forceFill(['status' => $status, 'end_reason' => $reason, 'ended_at' => $at, 'open_key' => null])->save();
        if ($status === SessionStatus::HoanThanh) {
            $this->ledger->record(XpSourceType::PhienGame, (string) $session->id, $session->user_id, $at, [
                'status' => $status->value, 'factor_pct' => (int) round((float) $session->level_factor * 100), 'card_count' => $session->card_count,
                'course_id' => $session->course_id,
            ]);
        }
    }
}
