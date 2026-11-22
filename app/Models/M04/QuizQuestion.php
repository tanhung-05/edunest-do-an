<?php

namespace App\Models\M04;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L24 quiz_questions — câu của quiz và bản chụp lúc gửi duyệt (CR-18).
 *
 * @property int $id
 * @property int $quiz_id
 * @property int $question_id
 * @property int $position
 * @property ?string $points
 * @property array<string, mixed>|null $snapshot
 * @property ?CarbonImmutable $snapshotted_at
 * @property ?CarbonImmutable $removed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class QuizQuestion extends Model
{
    protected $guarded = ['id', 'alive_key'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'points' => 'decimal:2',
            'snapshotted_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
