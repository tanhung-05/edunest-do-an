<?php

namespace App\Models\M29;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P10 M29 support_messages — một tin nhắn trong cuộc trò chuyện hỗ trợ.
 *
 * @property int $id
 * @property int $support_thread_id
 * @property ?int $sender_id
 * @property bool $from_staff
 * @property string $body
 * @property ?string $request_key
 * @property CarbonImmutable $created_at
 * @property-read ?User $sender
 */
class SupportMessage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['from_staff' => 'boolean', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return array{id: int, from_staff: bool, sender: string, body: string, at: string} */
    public function toChat(?string $guestName = null): array
    {
        return [
            'id' => $this->id,
            'from_staff' => $this->from_staff,
            // Phía học viên chỉ thấy "Nhân viên EduNest" kèm tên, không thấy email nhân sự.
            'sender' => $this->from_staff ? 'Nhân viên '.($this->sender->name ?? '') : ($this->sender->name ?? $guestName ?? 'Khách'),
            'body' => $this->body,
            'at' => $this->created_at->toIso8601String(),
        ];
    }
}
