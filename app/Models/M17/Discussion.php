<?php

namespace App\Models\M17;

use App\Enums\M17\DiscussionStatus;
use App\Models\M03\Course;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * N13 discussions — câu hỏi hỏi đáp theo bài (02c §1).
 *
 * @property int $id
 * @property int $course_id
 * @property string $lesson_key
 * @property int $lesson_id
 * @property int $user_id
 * @property string $body
 * @property ?int $video_second
 * @property DiscussionStatus $status
 * @property ?DiscussionStatus $status_before_hide
 * @property ?string $hidden_reason
 * @property ?int $hidden_by_id
 * @property ?CarbonImmutable $hidden_at
 * @property ?int $accepted_reply_id
 * @property int $follower_count
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Discussion extends Model
{
    protected $table = 'discussions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DiscussionStatus::class,
            'status_before_hide' => DiscussionStatus::class,
            'hidden_at' => 'immutable_datetime',
            'video_second' => 'integer',
            'course_id' => 'integer',
            'lesson_id' => 'integer',
            'user_id' => 'integer',
            'accepted_reply_id' => 'integer',
            'follower_count' => 'integer',
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

    /** @return HasMany<DiscussionReply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionReply::class);
    }
}
