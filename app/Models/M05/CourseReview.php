<?php

namespace App\Models\M05;

use App\Enums\M05\ReviewResult;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * L17 course_reviews — lần duyệt (chỉ thêm).
 *
 * @property int $id
 * @property int $course_version_id
 * @property int $reviewer_id
 * @property ReviewResult $result
 * @property ?string $reason
 * @property CarbonImmutable $created_at
 */
class CourseReview extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['result' => ReviewResult::class];
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return HasMany<ReviewComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ReviewComment::class);
    }
}
