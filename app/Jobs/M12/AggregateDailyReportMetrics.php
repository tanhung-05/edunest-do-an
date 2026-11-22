<?php

namespace App\Jobs\M12;

use App\Enums\M07\PaymentReconcileStatus;
use App\Enums\M07\PaymentTxnStatus;
use App\Enums\M10\AttemptStatus;
use App\Models\M03\Course;
use App\Models\M04\Quiz;
use App\Models\M07\Payment;
use App\Models\M08\Enrollment;
use App\Models\M10\AttemptAnswer;
use App\Models\M10\QuizAttempt;
use App\Models\M12\ReportMetric;
use App\Support\M10\QuizScore;
use App\Support\TestBarrier;
use App\Support\Tx;
use Brick\Math\BigRational;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;

#[Tries(4)]
#[Backoff([60, 300, 900])]
final class AggregateDailyReportMetrics implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $metricDate, public readonly CarbonImmutable $cutoffAt) {}

    public static function forDay(CarbonImmutable $day): self
    {
        $day = $day->setTimezone('Asia/Ho_Chi_Minh')->startOfDay();

        return new self($day->toDateString(), $day->addHours(2)->utc());
    }

    /**
     * Kỳ báo cáo là nửa mở [.., cutoffAt): mốc nguồn lưu tới giây (cắt bớt phần lẻ) và cutoffAt là giây nguyên,
     * nên t < cutoffAt tương đương floor(t) < cutoffAt — sự kiện lúc 02:00:00.800 không lọt vào kỳ vừa cắt (TV-08, AC-12-03).
     */
    public function handle(): void
    {
        Tx::run(function (): void {
            $rows = [$this->row('revenue', 'TOAN_HE_THONG', 0, $this->revenue())];
            foreach (Course::query()->where('created_at', '<', $this->cutoffAt)->lazyById() as $course) {
                $enrollments = Enrollment::query()->where('course_id', $course->id)->where('granted_at', '<', $this->cutoffAt);
                $total = (clone $enrollments)->count();
                $completed = $enrollments->where('completed_at', '<', $this->cutoffAt)->count();
                $rows[] = $this->row('completion_rate', 'KHOA_HOC', $course->id, [
                    'total' => $total, 'completed' => $completed,
                    'percent' => $total === 0 ? null : QuizScore::display(BigRational::of($completed)->dividedBy($total)),
                ]);
            }
            foreach (Quiz::query()->whereNotNull('course_version_id')->where('created_at', '<', $this->cutoffAt)->lazyById() as $quiz) {
                $rows[] = $this->row('quiz_summary', 'QUIZ_PHIEN_BAN', $quiz->id, $this->quiz($quiz));
            }

            foreach (array_chunk($rows, 250) as $chunk) {
                ReportMetric::query()->upsert($chunk, ['metric', 'scope_type', 'scope_id', 'metric_date'], ['value', 'computed_at']);
            }
            TestBarrier::reach('aggregate-daily-report-metrics.written');
        });
    }

    /** @return array{total: int, label: string, by_payment_date: array<string, int>} */
    private function revenue(): array
    {
        $days = [];
        $payments = Payment::query()->where('txn_status', PaymentTxnStatus::ThanhCong)
            ->where('reconcile_status', PaymentReconcileStatus::HopLe)
            ->where('succeeded_at', '<', $this->cutoffAt)
            ->where(fn ($q) => $q->whereNull('reconciled_at')->orWhere('reconciled_at', '<', $this->cutoffAt))
            ->where(fn ($q) => $q->whereNull('accepted_late_at')->orWhere('accepted_late_at', '<', $this->cutoffAt));
        foreach ($payments->lazyById() as $payment) {
            if ($payment->succeeded_at !== null) {
                $date = $payment->succeeded_at->setTimezone('Asia/Ho_Chi_Minh')->toDateString();
                $days[$date] = ($days[$date] ?? 0) + ($payment->received_amount ?? 0);
            }
        }
        ksort($days);

        return ['total' => array_sum($days), 'label' => 'Mô phỏng', 'by_payment_date' => $days];
    }

    /** @return array<string, mixed> */
    private function quiz(Quiz $quiz): array
    {
        $attempts = QuizAttempt::query()->where('quiz_id', $quiz->id)->whereNotNull('enrollment_id')
            ->whereIn('status', [AttemptStatus::DaNop, AttemptStatus::HetGio])
            ->where('finalized_at', '<', $this->cutoffAt);
        $best = [];
        $total = 0;
        $ids = [];
        foreach ((clone $attempts)->lazyById() as $attempt) {
            $total++;
            $ids[] = $attempt->id;
            $ratio = QuizScore::ratio($attempt);
            if ($ratio !== null && (! isset($best[$attempt->user_id]) || $ratio->isGreaterThan($best[$attempt->user_id]))) {
                $best[$attempt->user_id] = $ratio;
            }
        }

        $sum = BigRational::zero();
        foreach ($best as $ratio) {
            $sum = $sum->plus($ratio);
        }

        $answers = AttemptAnswer::query()->whereIn('quiz_attempt_id', $ids === [] ? [0] : $ids)
            ->groupBy('quiz_question_id')
            ->selectRaw('quiz_question_id, SUM(is_correct = 1) AS correct_count, SUM(answer IS NOT NULL AND JSON_TYPE(answer) <> ?) AS answered_count', ['NULL'])
            ->toBase()->get()->keyBy('quiz_question_id');

        $questions = [];
        if ($total > 0) {
            foreach ($quiz->items()->get() as $question) {
                $counts = $answers->get($question->id);
                $incorrect = $total - (int) ($counts->correct_count ?? 0);
                $questions[] = [
                    'quiz_question_id' => $question->id,
                    'position' => $question->position,
                    'finalized_attempts' => $total,
                    'incorrect_attempts' => $incorrect,
                    'incorrect_percent' => QuizScore::display(BigRational::of($incorrect)->dividedBy($total)),
                    'blank_attempts' => $total - (int) ($counts->answered_count ?? 0),
                ];
            }
        }
        // Các câu của cùng quiz dùng cùng mẫu số, nên cùng số sai cũng cùng tỷ lệ sai.
        usort($questions, fn (array $a, array $b): int => ($b['incorrect_attempts'] <=> $a['incorrect_attempts']) ?: ($a['position'] <=> $b['position']));

        return [
            'average_percent' => $best === [] ? null : QuizScore::display($sum->dividedBy(count($best))),
            'most_incorrect_questions' => array_slice($questions, 0, 10),
        ];
    }

    /** @param array<string, mixed> $value
     * @return array<string, int|string>
     */
    private function row(string $metric, string $scope, int $id, array $value): array
    {
        return [
            'metric' => $metric,
            'scope_type' => $scope,
            'scope_id' => $id,
            'metric_date' => $this->metricDate,
            'value' => json_encode($value, JSON_THROW_ON_ERROR),
            'computed_at' => $this->cutoffAt->format('Y-m-d H:i:s'),
        ];
    }
}
