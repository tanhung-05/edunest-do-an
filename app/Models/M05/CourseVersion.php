<?php

namespace App\Models\M05;

use App\Enums\M05\CourseVersionStatus;
use App\Models\M03\Course;
use App\Models\M04\Quiz;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * L12 course_versions — phiên bản nội dung (03 §3).
 *
 * @property int $id
 * @property int $course_id
 * @property int $version_no
 * @property CourseVersionStatus $status
 * @property ?int $source_version_id
 * @property ?string $summary
 * @property ?string $description
 * @property ?string $objectives
 * @property ?string $seo_title
 * @property ?string $seo_description
 * @property ?CarbonImmutable $submitted_at
 * @property ?int $submitted_by_id
 * @property ?CarbonImmutable $approved_at
 * @property ?CarbonImmutable $published_at
 * @property ?int $published_by_id
 * @property ?CarbonImmutable $superseded_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class CourseVersion extends Model
{
    protected $guarded = ['id', 'published_key', 'open_draft_key'];

    protected function casts(): array
    {
        return [
            'status' => CourseVersionStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<CourseSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->whereNull('removed_at')->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->whereNull('lessons.removed_at');
    }

    /** @return HasMany<Quiz, $this> */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    /** @return HasMany<CourseReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(CourseReview::class)->orderBy('id');
    }
}
