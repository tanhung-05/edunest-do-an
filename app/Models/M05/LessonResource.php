<?php

namespace App\Models\M05;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L16 lesson_resources — tài nguyên của bài.
 *
 * @property int $id
 * @property int $lesson_id
 * @property string $title
 * @property string $file_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $position
 * @property ?CarbonImmutable $removed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class LessonResource extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['removed_at' => 'datetime', 'size_bytes' => 'integer'];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
