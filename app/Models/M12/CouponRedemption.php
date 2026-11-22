<?php

namespace App\Models\M12;

use App\Enums\M12\CouponSlotStatus;
use App\Models\M07\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * N02 coupon_redemptions — chỗ và lượt dùng mã (CR-120).
 *
 * @property int $id
 * @property int $coupon_id
 * @property int $order_id
 * @property int $user_id
 * @property CouponSlotStatus $status
 * @property int $discount_amount
 * @property CarbonImmutable $order_expires_at
 * @property CarbonImmutable $held_at
 * @property ?CarbonImmutable $used_at
 * @property ?CarbonImmutable $released_at
 */
class CouponRedemption extends Model
{
    protected $table = 'coupon_redemptions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => CouponSlotStatus::class,
            'coupon_id' => 'integer',
            'order_id' => 'integer',
            'user_id' => 'integer',
            'discount_amount' => 'integer',
            'order_expires_at' => 'immutable_datetime',
            'held_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
