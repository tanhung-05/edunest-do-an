<?php

namespace App\Models\M25;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E24 ai_messages — một lượt hỏi (`USER`, mang khóa yêu cầu) hoặc phản hồi (`AI`, trỏ lượt hỏi). Phản hồi lỗi `LOI`.
 *
 * @property int $id
 * @property int $ai_conversation_id
 * @property string $role
 * @property ?int $reply_to_id
 * @property string $content
 * @property ?array<string, mixed> $meta
 * @property ?string $request_key
 * @property string $status
 * @property CarbonImmutable $created_at
 */
class AiMessage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
