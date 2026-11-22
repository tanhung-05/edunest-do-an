<?php

namespace App\Models\M17;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * N15 discussion_follows — lượt "Cũng thắc mắc".
 *
 * @property int $id
 * @property int $discussion_id
 * @property int $user_id
 * @property CarbonImmutable $created_at
 */
class DiscussionFollow extends Model
{
    public $timestamps = false;

    protected $table = 'discussion_follows';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'discussion_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'user_id' => 'integer',
        ];
    }
}
