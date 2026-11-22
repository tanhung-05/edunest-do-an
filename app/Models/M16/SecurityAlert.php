<?php

namespace App\Models\M16;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * N17 security_alerts — cảnh báo nhiều IP (CR-137).
 *
 * @property int $id
 * @property int $user_id
 * @property array<int, string> $ip_list
 * @property CarbonImmutable $detected_at
 */
class SecurityAlert extends Model
{
    public $timestamps = false;

    protected $table = 'security_alerts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'ip_list' => 'array',
            'detected_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
