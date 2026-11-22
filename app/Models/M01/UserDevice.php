<?php

namespace App\Models\M01;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $cookie_hash
 * @property string|null $browser
 * @property string|null $os
 * @property CarbonImmutable $first_seen_at
 * @property CarbonImmutable $last_active_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $remember_token_hash
 */
class UserDevice extends Model
{
    public $timestamps = false;

    protected $table = 'user_devices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'first_seen_at' => 'immutable_datetime',
            'last_active_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
