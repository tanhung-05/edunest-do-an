<?php

namespace App\Queries\M19;

use App\Enums\M04\QuestionType;
use App\Enums\M10\AttemptStatus;
use App\Models\M04\Quiz;
use App\Models\M04\QuizQuestion;
use App\Models\M10\AttemptAnswer;
use App\Models\M10\QuizAttempt;
use App\Support\M10\QuizScore;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * QT-20 (MH-GV-11 mở rộng): tiến độ, thống kê điểm theo điểm ghi nhận (lượt tốt nhất, BR-BC-09/17), phân bố 10 điểm,
 * từng câu theo lượt chốt gần nhất của mỗi người (BR-BC-10/11/18). Chỉ GV được giao xem từng câu (CR-149).
 */
final class QuizAnalytics
{
    /**
     * @param  Builder<QuizAttempt>  $attempts  mọi lượt thuộc phạm vi (một phiên bản quiz hoặc một kỳ thi)
     * @param  int  $assigned  số người được giao (ghi danh trên phiên bản, hoặc người đăng ký/dự thi)
     * @return array{progress: array<string, int>, scores: array<string, string|int|null>, buckets: list<array{label: string, count: int}>, questions: list<array<string, mixed>>}
     */
    public static function build(Quiz $quiz, Builder $attempts, int $assigned): array
    {
        /** @var Collection<int, Collection<int, QuizAttempt>> $byUser */
        $byUser = (clone $attempts)->orderBy('id')->get()->groupBy('user_id');
        $progress = ['assigned' => max($assigned, $byUser->count()), 'not_started' => 0, 'in_progress' => 0, 'submitted' => 0, 'timed_out' => 0];
        $best = [];
        $latest = [];
        foreach ($byUser as $userId => $rows) {
            $final = $rows->filter(fn (QuizAttempt $a) => in_array($a->status, [AttemptStatus::DaNop, AttemptStatus::HetGio], true));
            if ($final->isEmpty()) {
                $progress['in_progress']++;

                continue;
            }
            $final->contains(fn (QuizAttempt $a) => $a->status === AttemptStatus::DaNop) ? $progress['submitted']++ : $progress['timed_out']++;
            foreach ($final as $attempt) {
                $ratio = QuizScore::ratio($attempt);
                if ($ratio !== null && (! isset($best[$userId]) || $ratio->isGreaterThan($best[$userId]))) {
                    $best[$userId] = $ratio;
                }
            }
            $latest[] = $final->sortBy(fn (QuizAttempt $a) => [$a->finalized_at?->getTimestampMs() ?? 0, $a->id])->last()?->id;
        }
        $progress['not_started'] = max(0, $progress['assigned'] - $byUser->count());

        return [
            'progress' => $progress,
            'scores' => self::scores(array_values($best)),
            'buckets' => self::buckets(array_values($best)),
            'questions' => self::questions($quiz, array_values(array_filter($latest))),
        ];
    }

    /**
     * @param  list<BigRational>  $ratios
     * @return array{count: int, average: ?string, median: ?string, max: ?string, min: ?string}
     */
    private static function scores(array $ratios): array
    {
        if ($ratios === []) {
            return ['count' => 0, 'average' => null, 'median' => null, 'max' => null, 'min' => null];
        }
        usort($ratios, fn (BigRational $a, BigRational $b) => $a->compareTo($b));
        $n = count($ratios);
        $sum = array_reduce($ratios, fn (BigRational $carry, BigRational $r) => $carry->plus($r), BigRational::zero());
        $median = $n % 2 === 1 ? $ratios[intdiv($n, 2)] : $ratios[$n / 2 - 1]->plus($ratios[$n / 2])->dividedBy(2);

        return ['count' => $n, 'average' => QuizScore::display($sum->dividedBy($n)), 'median' => QuizScore::display($median),
            'max' => QuizScore::display($ratios[$n - 1]), 'min' => QuizScore::display($ratios[0])];
    }

    /**
     * Mười nhóm theo thang 10 điểm: [0,1), [1,2), … , [9,10] — 10 điểm rơi vào nhóm cuối.
     *
     * @param  list<BigRational>  $ratios
     * @return list<array{label: string, count: int}>
     */
    private static function buckets(array $ratios): array
    {
        $counts = array_fill(0, 10, 0);
        foreach ($ratios as $ratio) {
            $counts[min(9, (int) (string) $ratio->multipliedBy(10)->toScale(0, RoundingMode::Down))]++;
        }

        return array_map(fn (int $i) => ['label' => $i.'–'.($i + 1), 'count' => $counts[$i]], range(0, 9));
    }

    /**
     * @param  list<int>  $attemptIds
     * @return list<array<string, mixed>>
     */
    private static function questions(Quiz $quiz, array $attemptIds): array
    {
        $total = count($attemptIds);
        $answers = AttemptAnswer::query()->whereIn('quiz_attempt_id', $attemptIds === [] ? [0] : $attemptIds)->get()->groupBy('quiz_question_id');
        $rows = [];
        foreach ($quiz->items()->whereNull('removed_at')->orderBy('position')->get() as $question) {
            /** @var QuizQuestion $question */
            $given = $answers->get($question->id, collect());
            $correct = $given->where('is_correct', true)->count();
            $blank = $total - $given->filter(fn (AttemptAnswer $a) => $a->answer !== null)->count();
            $type = $question->snapshot['type'] ?? null;
            $choices = [];
            if (in_array($type, [QuestionType::MotDapAn->value, QuestionType::NhieuDapAn->value, QuestionType::DungSai->value], true)) {
                foreach (QuizScore::options($question) as $option) {
                    $picked = $given->filter(fn (AttemptAnswer $a) => is_array($a->answer) && in_array($option['id'], $a->answer, true))->count();
                    $choices[] = ['content' => $option['content'], 'is_correct' => $option['is_correct'], 'count' => $picked];
                }
            }
            $rows[] = [
                'position' => $question->position,
                'content' => mb_substr(strip_tags((string) ($question->snapshot['stem'] ?? '')), 0, 160),
                'type' => $type,
                'answered' => $total,
                'correct' => $correct,
                'wrong' => max(0, $total - $correct - $blank),
                'blank' => $blank,
                'accuracy' => $total === 0 ? null : QuizScore::display(BigRational::of($correct)->dividedBy($total)),
                'choices' => $choices,
            ];
        }

        return $rows;
    }

    /**
     * @param  array{progress: array<string, int>, scores: array<string, string|int|null>, buckets: list<array{label: string, count: int}>, questions: list<array<string, mixed>>}  $data
     * @return array<string, list<list<string|int|float|null>>>
     */
    public static function sheets(array $data): array
    {
        $p = $data['progress'];
        $s = $data['scores'];
        $questions = [['Câu', 'Nội dung', 'Đúng', 'Sai', 'Bỏ trống', 'Tỷ lệ đúng (%)']];
        foreach ($data['questions'] as $q) {
            $questions[] = [$q['position'], $q['content'], $q['correct'], $q['wrong'], $q['blank'], $q['accuracy']];
        }

        return [
            'Tổng quan' => [
                ['Chỉ số', 'Giá trị'],
                ['Được giao', $p['assigned']], ['Chưa bắt đầu', $p['not_started']], ['Đang làm', $p['in_progress']],
                ['Đã nộp', $p['submitted']], ['Hết giờ', $p['timed_out']],
                ['Số người có điểm', $s['count']], ['Trung bình (%)', $s['average']], ['Trung vị (%)', $s['median']],
                ['Cao nhất (%)', $s['max']], ['Thấp nhất (%)', $s['min']],
            ],
            'Phân bố điểm' => array_merge([['Khoảng điểm (thang 10)', 'Số người']], array_map(fn (array $b) => [$b['label'], $b['count']], $data['buckets'])),
            'Từng câu' => $questions,
        ];
    }
}
