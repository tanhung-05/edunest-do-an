<?php

namespace App\Models\M21;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E07 student_stats — tổng XP, cấp, số câu đúng, chuỗi dài nhất (BR-XP-32/33/46).
 *
 * @property int $user_id
 * @property int $total_xp
 * @property int $level
 * @property int $correct_answers
 * @property int $longest_streak
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class StudentStat extends Model
{
    protected $table = 'student_stats';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'total_xp' => 'integer',
            'level' => 'integer',
            'correct_answers' => 'integer',
            'longest_streak' => 'integer',
        ];
    }
}
