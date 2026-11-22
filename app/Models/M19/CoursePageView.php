<?php

namespace App\Models\M19;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * N18 course_page_views — lượt xem trang khóa (CR-113).
 *
 * @property int $id
 * @property int $course_id
 * @property string $session_hash
 * @property CarbonImmutable $view_date
 * @property CarbonImmutable $created_at
 */
class CoursePageView extends Model
{
    public $timestamps = false;

    protected $table = 'course_page_views';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'view_date' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'course_id' => 'integer',
        ];
    }
}
