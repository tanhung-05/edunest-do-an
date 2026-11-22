<?php

namespace App\Models\M21;

use App\Enums\M21\XpSourceType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E06 xp_sources — nguồn XP đã chốt, xử lý lại được (BR-XP-73, CR-159).
 *
 * @property int $id
 * @property int $user_id
 * @property XpSourceType $type
 * @property string $source_key
 * @property CarbonImmutable $occurred_at
 * @property array<string, mixed> $payload
 * @property ?CarbonImmutable $processed_at
 * @property int $attempts
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class XpSource extends Model
{
    protected $table = 'xp_sources';

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'type' => XpSourceType::class,
            'occurred_at' => 'immutable_datetime',
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }
}
