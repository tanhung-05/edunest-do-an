<?php

namespace App\Models\M04;

use App\Enums\M04\QuizKind;
use App\Models\M05\Lesson;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * L23 quizzes — cấu hình quiz bài học theo phiên bản.
 *
 * @property int $id
 * @property ?int $course_version_id
 * @property ?int $lesson_id
 * @property QuizKind $kind
 * @property ?int $pass_percent
 * @property ?int $duration_minutes
 * @property ?int $max_attempts
 * @property ?int $question_bank_id
 * @property ?CarbonImmutable $answers_open_at
 * @property ?string $title
 * @property ?int $owner_teacher_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Quiz extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => QuizKind::class,
            'pass_percent' => 'integer',
            'duration_minutes' => 'integer',
            'max_attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Câu còn sống của quiz.
     *
     * @return HasMany<QuizQuestion, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->whereNull('removed_at')->orderBy('position')->orderBy('id');
    }
}
