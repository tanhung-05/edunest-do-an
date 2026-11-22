<?php

namespace App\Models\M12;

use App\Models\M03\Course;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * N05 wishlists — khóa yêu thích.
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property CarbonImmutable $created_at
 */
class Wishlist extends Model
{
    public $timestamps = false;

    protected $table = 'wishlists';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'course_id' => 'integer',
            'user_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
