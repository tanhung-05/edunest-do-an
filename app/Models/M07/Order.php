<?php

namespace App\Models\M07;

use App\Enums\M07\OrderCancelReason;
use App\Enums\M07\OrderSource;
use App\Enums\M07\OrderStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * L28 orders — đơn hàng (03 §6).
 *
 * @property int $id
 * @property string $public_code
 * @property int $user_id
 * @property OrderStatus $status
 * @property OrderSource $source
 * @property int $subtotal
 * @property int $discount_total
 * @property int $total
 * @property ?int $coupon_id
 * @property ?int $affiliate_id
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $transfer_terms_accepted_at
 * @property ?OrderStatus $status_before_cxl
 * @property ?OrderCancelReason $cancel_reason
 * @property ?CarbonImmutable $cancelled_at
 * @property ?CarbonImmutable $expired_at
 * @property ?CarbonImmutable $completed_at
 * @property ?CarbonImmutable $first_completed_at
 * @property ?int $affiliate_rate_bp_snapshot
 * @property ?bool $affiliate_commission_eligible
 * @property ?CarbonImmutable $grant_stopped_at
 * @property ?int $grant_stopped_by_id
 * @property ?string $grant_stop_reason
 * @property ?int $grant_refund_payment_id
 * @property ?string $grant_ticket
 * @property ?CarbonImmutable $grant_ticket_at
 * @property ?int $grant_job_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Order extends Model
{
    protected $guarded = ['public_code', 'grant_ticket', 'grant_job_id', 'id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'source' => OrderSource::class,
            'status_before_cxl' => OrderStatus::class,
            'cancel_reason' => OrderCancelReason::class,
            'expires_at' => 'datetime',
            'transfer_terms_accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'completed_at' => 'datetime',
            'first_completed_at' => 'datetime',
            'grant_stopped_at' => 'datetime',
            'grant_ticket_at' => 'datetime',
            'affiliate_commission_eligible' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_code';
    }
}
