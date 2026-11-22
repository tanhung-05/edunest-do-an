<?php

namespace App\Models\M04;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * L21 question_options — lựa chọn.
 *
 * @property int $id
 * @property int $question_id
 * @property string $content
 * @property bool $is_correct
 * @property int $position
 * @property ?string $match_key
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class QuestionOption extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'position' => 'integer'];
    }
}
