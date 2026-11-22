<?php

namespace App\Models\M22;

use App\Enums\M20\SessionEndReason;
use App\Enums\M20\SessionStatus;
use App\Enums\M22\GameKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * E15 game_sessions — phiên mini game (CR-91/151).
 *
 * @property int $id
 * @property int $flashcard_set_id
 * @property int $user_id
 * @property int $enrollment_id
 * @property int $course_id
 * @property GameKind $kind
 * @property SessionStatus $status
 * @property ?SessionEndReason $end_reason
 * @property string $level_factor
 * @property int $card_count
 * @property int $current_position
 * @property CarbonImmutable $last_activity_at
 * @property CarbonImmutable $started_at
 * @property ?CarbonImmutable $ended_at
 * @property ?string $open_key
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class GameSession extends Model
{
    protected $table = 'game_sessions';

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return [
            'flashcard_set_id' => 'integer',
            'user_id' => 'integer',
            'enrollment_id' => 'integer',
            'course_id' => 'integer',
            'kind' => GameKind::class,
            'status' => SessionStatus::class,
            'end_reason' => SessionEndReason::class,
            'card_count' => 'integer',
            'current_position' => 'integer',
            'last_activity_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<GameCard, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(GameCard::class)->orderBy('position');
    }

    /** @return BelongsTo<FlashcardSet, $this> */
    public function set(): BelongsTo
    {
        return $this->belongsTo(FlashcardSet::class, 'flashcard_set_id');
    }
}
