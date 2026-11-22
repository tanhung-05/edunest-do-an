<?php

namespace App\Models\M22;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E13 flashcard_reviews — lịch ôn của cặp (học viên, thẻ) (BR-XP-55..61).
 *
 * @property int $id
 * @property int $flashcard_id
 * @property int $user_id
 * @property int $course_id
 * @property string $ease
 * @property ?string $interval_days
 * @property int $reps
 * @property ?CarbonImmutable $due_on
 * @property int $back_version
 * @property ?CarbonImmutable $last_reviewed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class FlashcardReview extends Model
{
    protected $table = 'flashcard_reviews';

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return [
            'flashcard_id' => 'integer',
            'ease' => 'decimal:2',
            'interval_days' => 'decimal:3',
            'user_id' => 'integer',
            'course_id' => 'integer',
            'reps' => 'integer',
            'due_on' => 'immutable_date',
            'back_version' => 'integer',
            'last_reviewed_at' => 'immutable_datetime',
        ];
    }
}
