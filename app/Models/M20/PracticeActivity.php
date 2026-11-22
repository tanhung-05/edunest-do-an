<?php

namespace App\Models\M20;

use App\Enums\M20\DrawMode;
use App\Models\M03\Course;
use App\Models\M04\QuestionBank;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * E01 activities — hoạt động luyện tập của khóa (BR-XP-70, CR-153).
 *
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property int $question_count
 * @property DrawMode $draw_mode
 * @property int $seconds_per_question
 * @property int $created_by_id
 * @property int $updated_by_id
 * @property ?CarbonImmutable $archived_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class PracticeActivity extends Model
{
    protected $table = 'activities';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'course_id' => 'integer',
            'question_count' => 'integer',
            'seconds_per_question' => 'integer',
            'draw_mode' => DrawMode::class,
            'archived_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsToMany<QuestionBank, $this> */
    public function banks(): BelongsToMany
    {
        return $this->belongsToMany(QuestionBank::class, 'activity_question_bank', 'activity_id', 'question_bank_id');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
