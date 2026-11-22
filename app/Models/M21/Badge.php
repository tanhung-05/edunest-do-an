<?php

namespace App\Models\M21;

use App\Enums\M21\BadgeRule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E09 badges — định nghĩa huy hiệu (BR-XP-43..47/71).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property ?string $description
 * @property BadgeRule $rule
 * @property int $threshold
 * @property bool $is_active
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Badge extends Model
{
    protected $table = 'badges';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rule' => BadgeRule::class,
            'threshold' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
