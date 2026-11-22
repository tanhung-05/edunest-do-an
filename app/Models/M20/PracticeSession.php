<?php

namespace App\Models\M20;

use App\Enums\M20\PracticeMode;
use App\Enums\M20\SessionEndReason;
use App\Enums\M20\SessionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * E03 activity_sessions — phiên luyện tập (BR-XP-05/06/14–16, CR-151/155).
 *
 * @property int $id
 * @property int $activity_id
 * @property int $enrollment_id
 * @property int $user_id
 * @property int $course_id
 * @property PracticeMode $mode
 * @property SessionStatus $status
 * @property ?SessionEndReason $end_reason
 * @property string $level_factor
 * @property ?int $lives
 * @property int $question_count
 * @property int $current_position
 * @property ?int $seconds_per_question
 * @property ?CarbonImmutable $question_deadline_at
 * @property CarbonImmutable $last_activity_at
 * @property CarbonImmutable $started_at
 * @property ?CarbonImmutable $ended_at
 * @property ?string $open_key
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class PracticeSession extends Model
{
    protected $table = 'activity_sessions';

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return [
            'activity_id' => 'integer',
            'enrollment_id' => 'integer',
            'user_id' => 'integer',
            'course_id' => 'integer',
            'mode' => PracticeMode::class,
            'status' => SessionStatus::class,
            'end_reason' => SessionEndReason::class,
            'lives' => 'integer',
            'question_count' => 'integer',
            'current_position' => 'integer',
            'seconds_per_question' => 'integer',
            'question_deadline_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<PracticeActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(PracticeActivity::class, 'activity_id');
    }

    /** @return HasMany<PracticeQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(PracticeQuestion::class, 'activity_session_id')->orderBy('position');
    }
}
