<?php

namespace App\Actions\M09;

use App\Enums\M05\LessonType;
use App\Enums\M09\Activity;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M09\LessonProgress;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M09\LearningAccess;
use App\Support\M09\LearningLocks;
use App\Support\TestBarrier;
use App\Support\Tx;
use Carbon\CarbonImmutable;

final class RecordWatchBeat
{
    /** @return array{progress: LessonProgress, counted: bool} */
    public function handle(User $user, Enrollment $enrollment, Lesson $lesson, int $positionMs, ?CarbonImmutable $receivedAt = null): array
    {
        $receivedAt = ($receivedAt ?? now())->floorMillisecond();
        TestBarrier::reach('record-watch-beat.received');

        return Tx::run(function () use ($user, $enrollment, $lesson, $positionMs, $receivedAt) {
            [$user, $enrollment] = LearningLocks::student($user, $enrollment);
            TestBarrier::reach('record-watch-beat.locked');
            LearningAccess::authorize($user, $enrollment, Activity::RecordWatchBeat, $lesson);
            $duration = $lesson->video_duration_ms ?? 0;
            if ($lesson->type !== LessonType::Video || $duration <= 0 || $positionMs < 0 || $positionMs > $duration) {
                throw new DomainRejection('Vị trí xem không hợp lệ.', 'position_ms');
            }

            $progress = FinishLesson::progress($enrollment, $lesson, $receivedAt);
            if ($progress->last_beat_at !== null && $receivedAt->lte($progress->last_beat_at)) {
                return ['progress' => $progress, 'counted' => false];
            }
            $previous = $progress->last_position_ms;
            $elapsed = $progress->last_beat_at?->diffInMilliseconds($receivedAt);
            $counted = $previous !== null && $elapsed !== null && $positionMs > $previous
                && $positionMs - $previous <= 2 * $elapsed + 5000;
            if ($counted) {
                $segments = self::merge([...($progress->watched_segments ?? []), [$previous, $positionMs]]);
                $progress->watched_segments = $segments;
                $progress->valid_watch_ms = array_sum(array_map(fn (array $segment): int => $segment[1] - $segment[0], $segments));
            }
            $progress->last_beat_at = $receivedAt;
            $progress->last_viewed_at = $receivedAt;
            $progress->last_position_ms = $positionMs;
            $progress->save();

            if ($progress->valid_watch_ms * 10 >= $duration * 9) {
                FinishLesson::apply($enrollment, $progress, now());
            }

            return ['progress' => $progress, 'counted' => $counted];
        });
    }

    /** @param list<array{int, int}> $segments
     * @return list<array{int, int}>
     */
    private static function merge(array $segments): array
    {
        sort($segments);
        $merged = [];
        foreach ($segments as [$start, $end]) {
            $last = array_key_last($merged);
            if ($last !== null && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($end, $merged[$last][1]);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }
}
