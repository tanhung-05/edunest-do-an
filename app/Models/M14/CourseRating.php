<?php

namespace App\Models\M14;

use App\Enums\M14\RatingStatus;
use App\Models\M03\Course;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * N11 course_ratings — đánh giá khóa và câu trả lời của giáo viên.
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property int $enrollment_id
 * @property int $stars
 * @property ?string $comment
 * @property RatingStatus $status
 * @property ?string $hidden_reason
 * @property ?CarbonImmutable $hidden_at
 * @property ?int $hidden_by_id
 * @property ?string $reply
 * @property ?int $replied_by_id
 * @property ?CarbonImmutable $replied_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class CourseRating extends Model
{
    protected $table = 'course_ratings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => RatingStatus::class,
            'user_id' => 'integer',
            'course_id' => 'integer',
            'stars' => 'integer',
            'hidden_at' => 'immutable_datetime',
            'replied_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
