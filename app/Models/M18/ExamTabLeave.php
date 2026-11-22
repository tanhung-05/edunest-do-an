<?php

namespace App\Models\M18;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * N21 exam_tab_leaves — ghi nhận rời tab (CR-77).
 *
 * @property int $id
 * @property int $quiz_attempt_id
 * @property CarbonImmutable $left_at
 * @property int $duration_ms
 */
class ExamTabLeave extends Model
{
    public $timestamps = false;

    protected $table = 'exam_tab_leaves';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'left_at' => 'immutable_datetime',
            'duration_ms' => 'integer',
        ];
    }
}
