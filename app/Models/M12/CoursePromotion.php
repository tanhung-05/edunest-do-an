<?php

namespace App\Models\M12;

use App\Models\M03\Course;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * N06 course_promotions — đợt khuyến mãi (02b §1).
 *
 * @property int $id
 * @property int $course_id
 * @property int $price
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property ?int $created_by_id
 * @property ?int $updated_by_id
 * @property ?CarbonImmutable $notified_at
 */
class CoursePromotion extends Model
{
    protected $table = 'course_promotions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'course_id' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'notified_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
