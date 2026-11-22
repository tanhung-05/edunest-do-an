<?php

namespace App\Models\M26;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E26 live_session_joins — lần bấm vào phòng đầu tiên của một học viên với một buổi (BR-MR-24).
 *
 * @property int $id
 * @property int $live_session_id
 * @property int $user_id
 * @property CarbonImmutable $first_clicked_at
 */
class LiveSessionJoin extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['first_clicked_at' => 'immutable_datetime'];
    }
}
