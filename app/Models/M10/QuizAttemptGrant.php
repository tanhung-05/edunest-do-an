<?php

namespace App\Models\M10;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $enrollment_id
 * @property int $quiz_id
 * @property int $extra_attempts
 * @property string $reason
 * @property int $granted_by_id
 * @property CarbonImmutable $created_at
 */
class QuizAttemptGrant extends Model
{
    public $timestamps = false;

    protected $table = 'quiz_attempt_grants';

    protected $guarded = ['id'];

    protected $attributes = ['extra_attempts' => 1];

    protected function casts(): array
    {
        return [
            'enrollment_id' => 'integer',
            'quiz_id' => 'integer',
            'extra_attempts' => 'integer',
            'granted_by_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
