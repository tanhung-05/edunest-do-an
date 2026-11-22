<?php

namespace App\Models\M03;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L11 course_authors — lịch sử giao soạn; current_key = người soạn hiện tại.
 *
 * @property int $id
 * @property int $course_id
 * @property int $user_id
 * @property ?CarbonImmutable $due_date
 * @property int $assigned_by_id
 * @property CarbonImmutable $assigned_at
 * @property ?CarbonImmutable $ended_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class CourseAuthor extends Model
{
    protected $guarded = ['id', 'current_key'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'assigned_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
