<?php

namespace App\Models\M17;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * N14 discussion_replies — câu trả lời hỏi đáp.
 *
 * @property int $id
 * @property int $discussion_id
 * @property int $user_id
 * @property string $body
 * @property ?CarbonImmutable $created_at
 */
class DiscussionReply extends Model
{
    protected $table = 'discussion_replies';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'discussion_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Discussion, $this> */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }
}
