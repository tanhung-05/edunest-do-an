<?php

namespace App\Models\M09;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $enrollment_id
 * @property int $lesson_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $completed_at
 * @property list<array{int, int}>|null $watched_segments
 * @property int $valid_watch_ms
 * @property int|null $last_position_ms
 * @property CarbonImmutable|null $last_beat_at
 * @property CarbonImmutable|null $last_viewed_at
 */
class LessonProgress extends Model
{
    public $timestamps = false;

    protected $table = 'lesson_progress';

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $attributes = ['valid_watch_ms' => 0];

    protected function casts(): array
    {
        return [
            'enrollment_id' => 'integer',
            'lesson_id' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'watched_segments' => 'array',
            'valid_watch_ms' => 'integer',
            'last_position_ms' => 'integer',
            'last_beat_at' => 'immutable_datetime',
            'last_viewed_at' => 'immutable_datetime',
        ];
    }
}
