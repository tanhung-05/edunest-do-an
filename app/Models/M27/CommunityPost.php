<?php

namespace App\Models\M27;

use App\Enums\M27\PostTopic;
use App\Models\M03\Course;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * E28 community_posts — bài trên bảng tin chung. Không xóa; ẩn/hiện có lý do (CR-12, BR-DG-26).
 *
 * @property int $id
 * @property int $user_id
 * @property PostTopic $topic
 * @property ?int $course_id
 * @property string $title
 * @property string $body
 * @property ?string $image_path
 * @property string $status
 * @property ?string $hidden_reason
 * @property ?CarbonImmutable $hidden_at
 * @property ?int $hidden_by_id
 * @property int $like_count
 * @property ?CarbonImmutable $edited_at
 * @property ?CarbonImmutable $created_at
 */
class CommunityPost extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['topic' => PostTopic::class, 'hidden_at' => 'immutable_datetime', 'edited_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<CommunityComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(CommunityComment::class)->orderBy('id');
    }
}
