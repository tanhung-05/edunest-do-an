<?php

namespace App\Models\M13;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * N09 activation_attempt_windows — cửa sổ lần nhập sai (CR-129).
 *
 * @property int $id
 * @property int $user_id
 * @property ?CarbonImmutable $window_started_at
 * @property int $failures
 * @property ?CarbonImmutable $locked_until
 */
class ActivationAttemptWindow extends Model
{
    protected $table = 'activation_attempt_windows';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'failures' => 'integer',
            'window_started_at' => 'immutable_datetime',
            'locked_until' => 'immutable_datetime',
        ];
    }
}
