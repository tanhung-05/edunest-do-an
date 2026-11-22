<?php

namespace App\Models\M18;

use App\Enums\M18\ExamAudience;
use App\Enums\M18\ExamStatus;
use App\Models\M04\Quiz;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * N19 exam_events — kỳ thi thử (02c KT-1..6).
 *
 * @property int $id
 * @property int $quiz_id
 * @property string $title
 * @property int $created_by_id
 * @property ExamStatus $status
 * @property CarbonImmutable $opens_at
 * @property CarbonImmutable $closes_at
 * @property int $duration_minutes
 * @property ExamAudience $audience
 * @property bool $requires_registration
 * @property ?CarbonImmutable $publish_at
 * @property ?CarbonImmutable $scheduled_at
 * @property ?CarbonImmutable $opened_at
 * @property ?CarbonImmutable $closed_at
 * @property ?CarbonImmutable $published_at
 * @property ?CarbonImmutable $reminded_at
 * @property ?CarbonImmutable $created_at
 */
class ExamEvent extends Model
{
    protected $table = 'exam_events';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ExamStatus::class,
            'audience' => ExamAudience::class,
            'requires_registration' => 'boolean',
            'opens_at' => 'immutable_datetime',
            'closes_at' => 'immutable_datetime',
            'publish_at' => 'immutable_datetime',
            'scheduled_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'reminded_at' => 'immutable_datetime',
            'duration_minutes' => 'integer',
            'quiz_id' => 'integer',
            'created_by_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** @return BelongsTo<User, $this> */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return HasMany<ExamEventCourse, $this> */
    public function courseLinks(): HasMany
    {
        return $this->hasMany(ExamEventCourse::class);
    }
}
