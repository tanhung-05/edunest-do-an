<?php

namespace App\Models\M05;

use App\Enums\M05\LessonType;
use App\Models\M04\Quiz;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * L15 lessons — bài học; lesson_key giữ qua phiên bản.
 *
 * @property int $id
 * @property int $course_section_id
 * @property int $course_version_id
 * @property string $lesson_key
 * @property string $title
 * @property ?string $objectives
 * @property LessonType $type
 * @property ?string $content
 * @property ?string $video_path
 * @property ?int $video_duration_ms
 * @property ?string $pdf_path
 * @property bool $is_required
 * @property bool $is_preview
 * @property bool $is_final_test
 * @property int $position
 * @property ?string $content_fingerprint
 * @property ?CarbonImmutable $removed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Lesson extends Model
{
    protected $guarded = ['id', 'alive_key', 'final_key'];

    protected function casts(): array
    {
        return [
            'type' => LessonType::class,
            'is_required' => 'boolean',
            'is_preview' => 'boolean',
            'is_final_test' => 'boolean',
            'video_duration_ms' => 'integer',
            'position' => 'integer',
            'removed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CourseSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    /** @return BelongsTo<CourseVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CourseVersion::class, 'course_version_id');
    }

    /** @return HasMany<LessonResource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(LessonResource::class)->whereNull('removed_at')->orderBy('position');
    }

    /** @return HasOne<Quiz, $this> */
    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }
}
