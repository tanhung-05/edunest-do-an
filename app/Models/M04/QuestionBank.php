<?php

namespace App\Models\M04;

use App\Models\M03\Course;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * L19 question_banks — ngân hàng câu hỏi của khóa.
 *
 * @property int $id
 * @property int $course_id
 * @property string $name
 * @property bool $is_confidential
 * @property int $created_by_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class QuestionBank extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_confidential' => 'boolean'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
