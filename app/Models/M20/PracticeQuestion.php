<?php

namespace App\Models\M20;

use App\Enums\M20\QuestionResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E04 activity_session_questions — câu đã chụp của phiên (BR-XP-08..13).
 *
 * @property int $id
 * @property int $activity_session_id
 * @property int $position
 * @property int $question_id
 * @property array<string, mixed> $snapshot
 * @property int $tries
 * @property bool $hint_used
 * @property ?QuestionResult $result
 * @property ?int $correct_on_try
 * @property mixed $answer
 * @property ?list<string> $request_keys
 * @property ?CarbonImmutable $opened_at
 * @property ?CarbonImmutable $deadline_at
 * @property ?CarbonImmutable $finished_at
 */
class PracticeQuestion extends Model
{
    protected $table = 'activity_session_questions';

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'activity_session_id' => 'integer',
            'position' => 'integer',
            'question_id' => 'integer',
            'snapshot' => 'array',
            'tries' => 'integer',
            'hint_used' => 'boolean',
            'result' => QuestionResult::class,
            'correct_on_try' => 'integer',
            'answer' => 'json',
            'request_keys' => 'array',
            'opened_at' => 'immutable_datetime',
            'deadline_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
