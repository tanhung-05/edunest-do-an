<?php

namespace App\Models\M10;

use App\Enums\M10\AttemptCloseReason;
use App\Enums\M10\AttemptStatus;
use App\Models\M04\Quiz;
use App\Models\M08\Enrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $quiz_id
 * @property int|null $enrollment_id
 * @property int|null $exam_registration_id
 * @property int|null $exam_event_id
 * @property int $user_id
 * @property int $attempt_no
 * @property AttemptStatus $status
 * @property AttemptCloseReason|null $close_reason
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $deadline_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $finalized_at
 * @property CarbonImmutable|null $event_at
 * @property string|null $score_earned
 * @property string|null $score_max
 * @property bool|null $passed
 * @property int|null $official_rank
 * @property int|null $open_key
 */
class QuizAttempt extends Model
{
    public $timestamps = false;

    protected $table = 'quiz_attempts';

    protected $guarded = ['id', 'open_key'];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $attributes = ['status' => 'DANG_LAM'];

    protected function casts(): array
    {
        return [
            'quiz_id' => 'integer',
            'enrollment_id' => 'integer',
            'exam_registration_id' => 'integer',
            'exam_event_id' => 'integer',
            'user_id' => 'integer',
            'attempt_no' => 'integer',
            'status' => AttemptStatus::class,
            'close_reason' => AttemptCloseReason::class,
            'started_at' => 'immutable_datetime',
            'deadline_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'finalized_at' => 'immutable_datetime',
            'event_at' => 'immutable_datetime',
            'score_earned' => 'decimal:4',
            'score_max' => 'decimal:4',
            'passed' => 'boolean',
            'official_rank' => 'integer',
            'open_key' => 'integer',
        ];
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AttemptAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }
}
