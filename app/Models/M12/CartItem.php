<?php

namespace App\Models\M12;

use App\Models\M03\Course;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * N04 cart_items — dòng giỏ.
 *
 * @property int $id
 * @property int $cart_id
 * @property int $course_id
 * @property CarbonImmutable $added_at
 */
class CartItem extends Model
{
    public $timestamps = false;

    protected $table = 'cart_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'course_id' => 'integer',
            'cart_id' => 'integer',
            'added_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
