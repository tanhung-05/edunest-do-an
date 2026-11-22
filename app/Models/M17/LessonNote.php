<?php

namespace App\Models\M17;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * N16 lesson_notes — ghi chú theo mốc video (CR-79/139).
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property string $lesson_key
 * @property int $lesson_id
 * @property ?int $video_second
 * @property string $body
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class LessonNote extends Model
{
    protected $table = 'lesson_notes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'video_second' => 'integer',
            'course_id' => 'integer',
            'lesson_id' => 'integer',
            'user_id' => 'integer',
        ];
    }
}
