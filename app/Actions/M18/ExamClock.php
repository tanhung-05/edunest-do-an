<?php

namespace App\Actions\M18;

use App\Actions\M21\XpLedger;
use App\Enums\M10\AttemptCloseReason;
use App\Enums\M10\AttemptStatus;
use App\Enums\M15\NotificationKind;
use App\Enums\M18\ExamAudience;
use App\Enums\M18\ExamStatus;
use App\Enums\M18\RegistrationStatus;
use App\Enums\M21\XpSourceType;
use App\Models\M04\Quiz;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use App\Models\M18\ExamConsent;
use App\Models\M18\ExamEvent;
use App\Models\M18\ExamEventCourse;
use App\Models\M18\ExamRanking;
use App\Models\M18\ExamRegistration;
use App\Models\User;
use App\Support\M15\Notifier;
use App\Support\Tx;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * TV-12 (02c KT-4..6): mở kỳ, đóng kỳ (chốt mọi lượt theo PX, xét mốc mở đáp án), công bố kèm xếp hạng và vinh danh,
 * nhắc 24 giờ trước giờ mở. Mỗi bước đọc lại dưới khóa kỳ nên chạy lặp không đổi kết quả.
 */
final class ExamClock
{
    public function __construct(private ExamTaking $taking) {}

    /** @return array{opened: int, closed: int, published: int, reminded: int, settled: int} */
    public function tick(?CarbonImmutable $now = null): array
    {
        $now ??= now()->floorMillisecond();
        $out = ['opened' => 0, 'closed' => 0, 'published' => 0, 'reminded' => 0, 'settled' => 0];
        foreach (ExamEvent::query()->where('status', ExamStatus::SapMo)->where('opens_at', '<=', $now)->pluck('id') as $id) {
            $out['opened'] += (int) $this->open($id, $now);
        }
        // Lượt quá hạn của kỳ đang mở chốt theo hạn lượt (E34) không phụ thuộc lúc tác vụ chạy.
        $overdue = QuizAttempt::query()->whereNotNull('exam_event_id')->where('status', AttemptStatus::DangLam)
            ->where('deadline_at', '<=', $now)->pluck('id');
        foreach ($overdue as $id) {
            $out['settled'] += (int) $this->settle($id, $now);
        }
        foreach (ExamEvent::query()->where('status', ExamStatus::DangMo)->where('closes_at', '<=', $now)->pluck('id') as $id) {
            $out['closed'] += (int) $this->close($id, $now);
        }
        foreach (ExamEvent::query()->where('status', ExamStatus::DaDong)->pluck('id') as $id) {
            $out['published'] += (int) $this->publish($id, $now);
        }
        $out['reminded'] = $this->remind($now);

        return $out;
    }

    public function open(int $id, CarbonImmutable $now): bool
    {
        return Tx::run(function () use ($id, $now) {
            $event = ExamEvent::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            // b5: đọc lại lịch; vừa dời sang sau thì không mở.
            if ($event->status !== ExamStatus::SapMo || $event->opens_at->gt($now)) {
                return false;
            }
            $event->update(['status' => ExamStatus::DangMo, 'opened_at' => $now]);

            return true;
        });
    }

    private function settle(int $attemptId, CarbonImmutable $now): bool
    {
        return Tx::run(function () use ($attemptId, $now) {
            $attempt = QuizAttempt::query()->findOrFail($attemptId);
            $event = ExamEvent::query()->whereKey($attempt->exam_event_id)->lockForUpdate()->firstOrFail();
            $attempt = QuizAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();

            return $this->taking->settleUnderLock($event, $attempt, $now)->status !== AttemptStatus::DangLam;
        });
    }

    /** c1–c3: `DANG_MO` → `DA_DONG`; chốt mọi lượt còn mở bằng PX; xét mốc mở đáp án dưới khóa đề. */
    public function close(int $id, CarbonImmutable $now): bool
    {
        return Tx::run(function () use ($id, $now) {
            $event = ExamEvent::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($event->status !== ExamStatus::DangMo || $event->closes_at->gt($now)) {
                return false;
            }
            $quiz = Quiz::query()->whereKey($event->quiz_id)->lockForUpdate()->firstOrFail();
            $open = QuizAttempt::query()->where('exam_event_id', $event->id)->where('status', AttemptStatus::DangLam)->orderBy('id')->lockForUpdate()->get();
            foreach ($open as $attempt) {
                $this->taking->settleUnderLock($event, $attempt, $now->gt($event->closes_at) ? $now : $event->closes_at);
            }
            $event->update(['status' => ExamStatus::DaDong, 'closed_at' => $now]);
            ExamEvents::markAnswersOpen($quiz);

            return true;
        });
    }

    /** c4–c9: công bố khi đã chốt xong và tới giờ hẹn; xếp hạng 1,2,2,4 (CR-72); chụp đồng ý công khai tên của top 10. */
    public function publish(int $id, CarbonImmutable $now): bool
    {
        $published = Tx::run(function () use ($id, $now) {
            $event = ExamEvent::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($event->status !== ExamStatus::DaDong || ($event->publish_at !== null && $event->publish_at->gt($now))) {
                return false;
            }
            if (QuizAttempt::query()->where('exam_event_id', $event->id)->where('status', AttemptStatus::DangLam)->exists()) {
                return false;
            }
            $this->rank($event);
            $event->update(['status' => ExamStatus::DaCongBo, 'published_at' => $now]);
            // QT-23 A3/E39: hạng chính thức sang nguồn huy hiệu Top kỳ thi (không XP), cùng lần công bố.
            foreach (ExamRanking::query()->where('exam_event_id', $event->id)->get() as $ranking) {
                app(XpLedger::class)->record(XpSourceType::KyThi, $event->id.':'.$ranking->user_id, $ranking->user_id, $now,
                    ['exam_event_id' => $event->id, 'rank' => $ranking->rank]);
            }

            return true;
        });
        if ($published) {
            DB::afterCommit(function () use ($id) {
                $event = ExamEvent::query()->findOrFail($id);
                $attempts = QuizAttempt::query()->where('exam_event_id', $id)->pluck('id', 'user_id');
                $users = User::query()->whereIn('id', $attempts->keys())->get();
                foreach ($users as $user) {
                    // UAT 22/11: dẫn thẳng tới kết quả bài làm của chính người nhận (học viên hay nhân sự dự thi).
                    Notifier::send($user, NotificationKind::KyThi, 'Có kết quả kỳ thi', 'Kỳ "'.$event->title.'" đã công bố kết quả.',
                        route('exams.attempts.result', $attempts[$user->id], false), 'exam-published:'.$id.':'.$user->id);
                }
            });
        }

        return $published;
    }

    /** c5: xếp hạng trên lượt đã chốt của người được xếp hạng; điểm chưa làm tròn ↓, thời gian làm ↑, mốc sự kiện chốt ↑. */
    public function rank(ExamEvent $event): void
    {
        ExamRanking::query()->where('exam_event_id', $event->id)->delete();
        $rows = [];
        $attempts = QuizAttempt::query()->where('exam_event_id', $event->id)->whereIn('status', [AttemptStatus::DaNop, AttemptStatus::HetGio])->get();
        foreach ($attempts as $attempt) {
            $user = User::query()->findOrFail($attempt->user_id);
            if (ExamTaking::unranked($user, $event)) {
                continue;
            }
            $ratio = BigRational::of($attempt->score_max ?? '0')->isZero() ? BigRational::zero()
                : BigRational::of($attempt->score_earned ?? '0')->dividedBy($attempt->score_max ?? '1');
            $end = $attempt->status === AttemptStatus::HetGio ? $attempt->deadline_at : ($attempt->event_at ?? $attempt->closed_at ?? $attempt->deadline_at);
            $rows[] = [
                'attempt' => $attempt, 'ratio' => $ratio,
                'duration' => (int) $attempt->started_at->diffInMilliseconds($end, true),
                'event_at' => $attempt->event_at ?? $end,
            ];
        }
        $cmp = function (array $a, array $b): int {
            $c = $b['ratio']->compareTo($a['ratio']);
            if ($c !== 0) {
                return $c;
            }
            $c = $a['duration'] <=> $b['duration'];

            return $c !== 0 ? $c : $a['event_at']->getTimestampMs() <=> $b['event_at']->getTimestampMs();
        };
        usort($rows, $cmp);
        $consents = ExamConsent::query()->where('exam_event_id', $event->id)->where('consent', true)->pluck('user_id')->flip();
        $rank = 0;
        foreach ($rows as $i => $row) {
            if ($i === 0 || $cmp($rows[$i - 1], $row) !== 0) {
                $rank = $i + 1;
            }
            /** @var QuizAttempt $attempt */
            $attempt = $row['attempt'];
            ExamRanking::query()->create([
                'exam_event_id' => $event->id, 'user_id' => $attempt->user_id, 'quiz_attempt_id' => $attempt->id, 'rank' => $rank,
                'score_ratio' => (string) $row['ratio']->toScale(8, RoundingMode::Down), 'duration_ms' => $row['duration'],
                'event_at' => $row['event_at'], 'show_name' => $rank <= 10 && $consents->has($attempt->user_id),
            ]);
            $attempt->forceFill(['official_rank' => $rank])->save();
        }
    }

    /** b4, CR-51/143: một nhắc mỗi tài khoản trước giờ mở 24 giờ. */
    private function remind(CarbonImmutable $now): int
    {
        $sent = 0;
        $events = ExamEvent::query()->where('status', ExamStatus::SapMo)->whereNull('reminded_at')
            ->where('opens_at', '<=', $now->addDay())->where('opens_at', '>', $now)->get();
        foreach ($events as $event) {
            $userIds = $event->requires_registration || $event->audience === ExamAudience::CongKhai
                ? ExamRegistration::query()->where('exam_event_id', $event->id)->where('status', RegistrationStatus::DaDangKy)->pluck('user_id')
                : Enrollment::query()->whereIn('course_id', ExamEventCourse::query()->where('exam_event_id', $event->id)->select('course_id'))
                    ->whereNull('revoked_at')->pluck('user_id')->unique();
            foreach (User::query()->whereIn('id', $userIds)->get() as $user) {
                Notifier::send($user, NotificationKind::KyThi, 'Kỳ thi sắp mở', 'Kỳ "'.$event->title.'" mở lúc '
                    .$event->opens_at->setTimezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y').'.', route('exams.show', $event),
                    'exam-remind:'.$event->id.':'.$user->id);
                $sent++;
            }
            $event->forceFill(['reminded_at' => $now])->save();
        }

        return $sent;
    }

    /** Lý do chốt hiển thị (dùng ở trang kết quả). */
    public static function reasonLabel(?AttemptCloseReason $reason): ?string
    {
        return $reason?->label();
    }
}
