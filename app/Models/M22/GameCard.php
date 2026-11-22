<?php

namespace App\Models\M22;

use App\Enums\M22\CardResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E16 game_session_cards — lượt thẻ trong game (BR-XP-65..69).
 *
 * @property int $id
 * @property int $game_session_id
 * @property int $position
 * @property int $flashcard_id
 * @property array<string, mixed> $snapshot
 * @property ?CardResult $result
 * @property ?string $answer
 * @property ?string $request_id
 * @property ?CarbonImmutable $answered_at
 */
class GameCard extends Model
{
    protected $table = 'game_session_cards';

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'game_session_id' => 'integer',
            'position' => 'integer',
            'flashcard_id' => 'integer',
            'snapshot' => 'array',
            'result' => CardResult::class,
            'answered_at' => 'immutable_datetime',
        ];
    }
}
