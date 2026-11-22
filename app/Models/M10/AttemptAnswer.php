<?php

namespace App\Models\M10;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $quiz_attempt_id
 * @property int $quiz_question_id
 * @property mixed $answer
 * @property CarbonImmutable $received_at
 * @property bool|null $is_correct
 * @property int|null $parts_correct
 * @property int|null $parts_total
 * @property string|null $points_awarded
 */
class AttemptAnswer extends Model
{
    public $timestamps = false;

    protected $table = 'attempt_answers';

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return [
            'quiz_attempt_id' => 'integer',
            'quiz_question_id' => 'integer',
            'answer' => 'json',
            'received_at' => 'immutable_datetime',
            'is_correct' => 'boolean',
            'parts_correct' => 'integer',
            'parts_total' => 'integer',
            'points_awarded' => 'decimal:2',
        ];
    }
}
