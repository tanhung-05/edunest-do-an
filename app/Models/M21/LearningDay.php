<?php

namespace App\Models\M21;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E08 learning_days — ngày học theo giờ Việt Nam (BR-XP-38..40).
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $day
 * @property int $xp_source_id
 */
class LearningDay extends Model
{
    protected $table = 'learning_days';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'day' => 'immutable_date',
            'xp_source_id' => 'integer',
        ];
    }
}
