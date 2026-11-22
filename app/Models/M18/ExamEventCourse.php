<?php

namespace App\Models\M18;

use Illuminate\Database\Eloquent\Model;

/**
 * Khóa chỉ định của kỳ thi.
 *
 * @property int $id
 * @property int $exam_event_id
 * @property int $course_id
 */
class ExamEventCourse extends Model
{
    public $timestamps = false;

    protected $table = 'exam_event_courses';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'exam_event_id' => 'integer',
            'course_id' => 'integer',
        ];
    }
}
