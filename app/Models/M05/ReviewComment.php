<?php

namespace App\Models\M05;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L18 review_comments — nhận xét theo bài.
 *
 * @property int $id
 * @property int $course_review_id
 * @property ?int $lesson_id
 * @property string $comment
 * @property CarbonImmutable $created_at
 */
class ReviewComment extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
