<?php

namespace App\Models\M05;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * L14 course_sections — chương.
 *
 * @property int $id
 * @property int $course_version_id
 * @property string $title
 * @property int $position
 * @property ?CarbonImmutable $removed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class CourseSection extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['removed_at' => 'datetime', 'position' => 'integer'];
    }

    /** @return BelongsTo<CourseVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CourseVersion::class, 'course_version_id');
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->whereNull('removed_at')->orderBy('position')->orderBy('id');
    }
}
