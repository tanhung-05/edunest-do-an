<?php

namespace App\Queries\M18;

use App\Enums\M18\ExamStatus;
use App\Models\M18\ExamEvent;
use App\Models\M18\ExamRanking;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Bảng xếp hạng và vinh danh (QT-19 c5–c9, CR-72/73): tên chỉ hiện khi đồng ý công khai và trong top 10. */
final class ExamBoard
{
    /** @return list<array{rank: int, name: string, percent: string, mine: bool}> */
    public static function top(ExamEvent $event, ?User $viewer, int $limit = 10): array
    {
        return array_values(ExamRanking::query()->where('exam_event_id', $event->id)->where('rank', '<=', $limit)->orderBy('rank')->orderBy('id')
            ->with('user:id,name')->get()->map(fn (ExamRanking $r) => [
                'rank' => $r->rank,
                'name' => $r->show_name || $r->user_id === $viewer?->id ? (string) $r->user?->name : 'Ẩn danh',
                'percent' => self::percent($r->score_ratio),
                'mine' => $r->user_id === $viewer?->id,
            ])->all());
    }

    public static function percent(string $ratio): string
    {
        return (string) BigDecimal::of($ratio)->multipliedBy(100)->toScale(1, RoundingMode::HalfUp);
    }

    /**
     * MH-KH-01: kỳ công bố gần nhất có người được vinh danh.
     *
     * @return array{title: string, published_at: ?string, people: list<array{rank: int, name: string, percent: string}>}|null
     */
    public static function hallOfFame(): ?array
    {
        $event = ExamEvent::query()->where('status', ExamStatus::DaCongBo)
            ->whereExists(fn ($q) => $q->from('exam_rankings')->whereColumn('exam_rankings.exam_event_id', 'exam_events.id')->where('show_name', true))
            ->orderByDesc('published_at')->orderByDesc('id')->first();
        if ($event === null) {
            return null;
        }

        return [
            'title' => $event->title,
            'published_at' => $event->published_at?->toIso8601String(),
            'people' => array_values(ExamRanking::query()->where('exam_event_id', $event->id)->where('show_name', true)->orderBy('rank')->orderBy('id')
                ->with('user:id,name')->limit(10)->get()->map(fn (ExamRanking $r) => [
                    'rank' => $r->rank, 'name' => (string) $r->user?->name, 'percent' => self::percent($r->score_ratio),
                ])->all()),
        ];
    }
}
