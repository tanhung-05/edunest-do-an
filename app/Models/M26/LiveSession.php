<?php

namespace App\Models\M26;

use App\Models\M03\Course;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * E25 live_sessions — buổi học trực tiếp của một khóa (QT-28). Không xóa; hủy bằng `cancelled_at`.
 *
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property ?string $description
 * @property CarbonImmutable $starts_at
 * @property int $duration_minutes
 * @property string $meeting_url
 * @property int $schedule_rev
 * @property ?CarbonImmutable $cancelled_at
 * @property ?int $cancelled_by_id
 * @property int $created_by_id
 * @property int $updated_by_id
 */
class LiveSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }

    public function endsAt(): CarbonImmutable
    {
        return $this->starts_at->addMinutes($this->duration_minutes);
    }

    /** BR-MR-23, CR-102: từ 10 phút trước giờ bắt đầu tới hết giờ kết thúc, gồm hai đầu. */
    public function joinable(CarbonImmutable $at): bool
    {
        return $this->cancelled_at === null && $at->gte($this->starts_at->subMinutes(10)) && $at->lte($this->endsAt());
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<LiveSessionJoin, $this> */
    public function joins(): HasMany
    {
        return $this->hasMany(LiveSessionJoin::class);
    }

    /** @return HasOne<LiveSessionRecording, $this> */
    public function currentRecording(): HasOne
    {
        return $this->hasOne(LiveSessionRecording::class, 'current_for_session_id');
    }
}
