<?php

namespace App\Models\M05;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Lịch sử chuyển phiên bản (QT-21).
 *
 * @property int $id
 * @property int $enrollment_id
 * @property int $from_version_id
 * @property int $to_version_id
 * @property int $kept_lessons
 * @property CarbonImmutable $switched_at
 */
class EnrollmentVersionSwitch extends Model
{
    public $timestamps = false;

    protected $table = 'enrollment_version_switches';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'switched_at' => 'immutable_datetime',
            'kept_lessons' => 'integer',
        ];
    }
}
