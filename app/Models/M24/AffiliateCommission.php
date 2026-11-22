<?php

namespace App\Models\M24;

use App\Enums\M24\CommissionStatus;
use App\Models\M07\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * affiliate_commissions — tối đa một hoa hồng mỗi đơn; tổng và tỷ lệ chụp ở lần hoàn tất đầu (BR-TT-73/74, CR-95/165).
 *
 * @property int $id
 * @property int $order_id
 * @property int $affiliate_id
 * @property int $base_amount
 * @property int $rate_bp
 * @property int $amount
 * @property CommissionStatus $status
 * @property CarbonImmutable $recorded_at
 * @property CarbonImmutable|null $paid_at
 * @property int|null $paid_by_id
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancel_reason
 */
class AffiliateCommission extends Model
{
    protected $table = 'affiliate_commissions';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'affiliate_id' => 'integer',
            'base_amount' => 'integer',
            'rate_bp' => 'integer',
            'amount' => 'integer',
            'status' => CommissionStatus::class,
            'recorded_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'paid_by_id' => 'integer',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
