<?php

namespace App\Models\M22;

use App\Enums\M22\ReviewGrade;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E14 flashcard_review_logs — từng lượt tự chấm.
 *
 * @property int $id
 * @property int $flashcard_review_id
 * @property string $request_id
 * @property ReviewGrade $grade
 * @property string $ease_before
 * @property string $ease_after
 * @property ?string $interval_before
 * @property string $interval_after
 * @property CarbonImmutable $due_on
 * @property CarbonImmutable $reviewed_at
 */
class FlashcardReviewLog extends Model
{
    protected $table = 'flashcard_review_logs';

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'flashcard_review_id' => 'integer',
            'grade' => ReviewGrade::class,
            'due_on' => 'immutable_date',
            'reviewed_at' => 'immutable_datetime',
        ];
    }
}
