<?php

namespace App\Models\M25;

use App\Enums\M25\AiKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * E23 ai_conversations — hội thoại AI của một người dùng (hoặc một phiên Khách), hỏi trong bài gắn một bài.
 *
 * @property int $id
 * @property AiKind $kind
 * @property ?int $user_id
 * @property ?string $guest_key
 * @property ?int $enrollment_id
 * @property ?int $lesson_id
 * @property ?int $discussion_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AiConversation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['kind' => AiKind::class];
    }

    /** @return HasMany<AiMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('id');
    }
}
