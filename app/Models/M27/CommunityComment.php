<?php

namespace App\Models\M27;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * E29 community_comments — bình luận; trạng thái riêng, hiển thị theo bài cha (CR-135).
 *
 * @property int $id
 * @property int $community_post_id
 * @property int $user_id
 * @property string $body
 * @property string $status
 * @property ?string $hidden_reason
 * @property ?CarbonImmutable $hidden_at
 * @property ?int $hidden_by_id
 * @property ?CarbonImmutable $created_at
 */
class CommunityComment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['hidden_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<CommunityPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }
}
