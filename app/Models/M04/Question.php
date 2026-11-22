<?php

namespace App\Models\M04;

use App\Enums\M04\QuestionDifficulty;
use App\Enums\M04\QuestionSource;
use App\Enums\M04\QuestionStatus;
use App\Enums\M04\QuestionType;
use App\Models\M02\Tag;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * L20 questions — câu hỏi (không xóa, CR-107).
 *
 * @property int $id
 * @property int $question_bank_id
 * @property QuestionType $type
 * @property QuestionDifficulty $difficulty
 * @property string $stem
 * @property ?string $image_path
 * @property string $points
 * @property ?string $hint
 * @property ?string $explanation
 * @property QuestionStatus $status
 * @property QuestionSource $source
 * @property ?CarbonImmutable $ai_first_approved_at
 * @property int $created_by_id
 * @property int $updated_by_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Question extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'difficulty' => QuestionDifficulty::class,
            'status' => QuestionStatus::class,
            'source' => QuestionSource::class,
            'points' => 'decimal:2',
            'ai_first_approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<QuestionBank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    /** @return HasMany<QuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('position');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'question_tag');
    }
}
