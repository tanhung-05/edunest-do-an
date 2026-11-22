<?php

namespace Database\Factories\M09;

use App\Enums\M05\LessonType;
use App\Models\M08\Enrollment;
use App\Models\M09\LessonProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LessonProgress> */
final class LessonProgressFactory extends Factory
{
    protected $model = LessonProgress::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'enrollment_id' => LearningEnrollmentFactory::new(),
            'lesson_id' => fn (array $a) => LearningEnrollmentFactory::lesson(
                Enrollment::query()->whereKey($a['enrollment_id'])->firstOrFail()->course_version_id, LessonType::Video,
            )->id,
            'started_at' => now(), 'valid_watch_ms' => 0,
        ];
    }

    public function watched(int $milliseconds = 540000): static
    {
        return $this->state(fn () => [
            'watched_segments' => [[0, $milliseconds]],
            'valid_watch_ms' => $milliseconds, 'last_position_ms' => $milliseconds,
            'last_beat_at' => now(), 'last_viewed_at' => now(),
            'completed_at' => $milliseconds >= 540000 ? now() : null,
        ]);
    }
}
