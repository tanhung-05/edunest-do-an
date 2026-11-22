<?php

namespace App\Models\M22;

use App\Models\M03\Course;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * E11 flashcard_sets — bộ thẻ của khóa, do Giáo viên tự tạo (BR-XP-53, CR-92).
 *
 * @property int $id
 * @property int $course_id
 * @property ?int $course_section_id
 * @property string $title
 * @property ?string $description
 * @property int $created_by_id
 * @property int $updated_by_id
 * @property ?CarbonImmutable $archived_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class FlashcardSet extends Model
{
    protected $table = 'flashcard_sets';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'course_id' => 'integer',
            'course_section_id' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<Flashcard, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Flashcard::class)->whereNull('archived_at')->orderBy('position')->orderBy('id');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
