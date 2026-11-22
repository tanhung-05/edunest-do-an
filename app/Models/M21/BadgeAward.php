<?php

namespace App\Models\M21;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * E10 badge_user — huy hiệu đã trao, mỗi học viên một lần (BR-XP-47).
 *
 * @property int $id
 * @property int $badge_id
 * @property int $user_id
 * @property ?int $xp_source_id
 * @property CarbonImmutable $awarded_at
 */
class BadgeAward extends Model
{
    protected $table = 'badge_user';

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'badge_id' => 'integer',
            'user_id' => 'integer',
            'xp_source_id' => 'integer',
            'awarded_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Badge, $this> */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}
