<?php

namespace App\Models\M21;

use App\Enums\M21\XpRule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E05 xp_transactions — sổ XP, mỗi khoản một khóa duy nhất (BR-XP-49).
 *
 * @property int $id
 * @property int $user_id
 * @property int $xp_source_id
 * @property XpRule $rule
 * @property int $amount
 * @property string $unique_key
 * @property ?CarbonImmutable $streak_start
 * @property CarbonImmutable $occurred_at
 * @property ?CarbonImmutable $created_at
 */
class XpTransaction extends Model
{
    protected $table = 'xp_transactions';

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'xp_source_id' => 'integer',
            'rule' => XpRule::class,
            'amount' => 'integer',
            'streak_start' => 'immutable_date',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
