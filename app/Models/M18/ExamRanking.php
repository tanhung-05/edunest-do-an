<?php

namespace App\Models\M18;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Xếp hạng chính thức chụp lúc công bố (BR-TH-22..31).
 *
 * @property int $id
 * @property int $exam_event_id
 * @property int $user_id
 * @property int $quiz_attempt_id
 * @property int $rank
 * @property string $score_ratio
 * @property int $duration_ms
 * @property CarbonImmutable $event_at
 * @property bool $show_name
 */
class ExamRanking extends Model
{
    public $timestamps = false;

    protected $table = 'exam_rankings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'duration_ms' => 'integer',
            'event_at' => 'immutable_datetime',
            'show_name' => 'boolean',
            'user_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
