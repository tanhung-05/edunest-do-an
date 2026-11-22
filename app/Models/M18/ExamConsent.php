<?php

namespace App\Models\M18;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Đồng ý công khai tên theo kỳ (CR-73).
 *
 * @property int $id
 * @property int $exam_event_id
 * @property int $user_id
 * @property bool $consent
 * @property CarbonImmutable $recorded_at
 */
class ExamConsent extends Model
{
    public $timestamps = false;

    protected $table = 'exam_consents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'consent' => 'boolean',
            'recorded_at' => 'immutable_datetime',
            'user_id' => 'integer',
        ];
    }
}
