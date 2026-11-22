<?php

namespace App\Models\M07;

use App\Enums\M07\OrderItemKind;
use App\Enums\M07\OrderItemStatus;
use App\Models\M03\Course;
use App\Models\M08\Enrollment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L29 order_items — dòng đơn (03 §8).
 *
 * @property int $id
 * @property int $order_id
 * @property int $course_id
 * @property OrderItemKind $kind
 * @property OrderItemStatus $status
 * @property string $course_title
 * @property int $list_price
 * @property ?int $promotion_id
 * @property int $effective_price
 * @property int $allocated_discount
 * @property int $final_amount
 * @property ?int $access_months
 * @property int $sort_order
 * @property ?int $bundle_id
 * @property ?int $enrollment_id
 * @property ?CarbonImmutable $expiry_before
 * @property ?CarbonImmutable $expiry_after
 * @property ?CarbonImmutable $granted_at
 * @property int $grant_attempts
 * @property ?CarbonImmutable $grant_last_attempt_at
 * @property ?CarbonImmutable $grant_next_attempt_at
 * @property ?string $grant_run_token
 * @property ?CarbonImmutable $grant_run_expires_at
 * @property ?string $grant_last_error
 * @property ?string $grant_block_code
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => OrderItemKind::class,
            'status' => OrderItemStatus::class,
            'expiry_before' => 'datetime',
            'expiry_after' => 'datetime',
            'granted_at' => 'datetime',
            'grant_last_attempt_at' => 'datetime',
            'grant_next_attempt_at' => 'datetime',
            'grant_run_expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
