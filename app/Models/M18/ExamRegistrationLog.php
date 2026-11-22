<?php

namespace App\Models\M18;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Dấu vết đổi hiệu lực đăng ký (CR-145).
 *
 * @property int $id
 * @property int $exam_registration_id
 * @property string $action
 * @property int $actor_id
 * @property CarbonImmutable $at
 */
class ExamRegistrationLog extends Model
{
    public $timestamps = false;

    protected $table = 'exam_registration_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'at' => 'immutable_datetime',
        ];
    }
}
