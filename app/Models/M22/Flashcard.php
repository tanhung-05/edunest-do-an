<?php

namespace App\Models\M22;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * E12 flashcards — thẻ ghi nhớ; sửa mặt sau tăng back_version (CR-90/161).
 *
 * @property int $id
 * @property int $flashcard_set_id
 * @property int $position
 * @property string $front
 * @property string $back
 * @property ?string $image_path
 * @property ?string $speech_lang
 * @property int $back_version
 * @property ?CarbonImmutable $archived_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Flashcard extends Model
{
    protected $table = 'flashcards';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'flashcard_set_id' => 'integer',
            'position' => 'integer',
            'back_version' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<FlashcardSet, $this> */
    public function set(): BelongsTo
    {
        return $this->belongsTo(FlashcardSet::class, 'flashcard_set_id');
    }
}
