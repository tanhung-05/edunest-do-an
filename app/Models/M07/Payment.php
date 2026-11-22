<?php

namespace App\Models\M07;

use App\Enums\M07\PaymentChannel;
use App\Enums\M07\PaymentReconcileStatus;
use App\Enums\M07\PaymentTxnStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * L30 payments — khoản thanh toán (03 §7).
 *
 * @property int $id
 * @property int $order_id
 * @property string $transaction_code
 * @property int $requested_amount
 * @property ?int $received_amount
 * @property PaymentChannel $channel
 * @property ?int $source_seq
 * @property PaymentTxnStatus $txn_status
 * @property ?PaymentReconcileStatus $reconcile_status
 * @property CarbonImmutable $terms_accepted_at
 * @property bool $is_late
 * @property ?CarbonImmutable $succeeded_at
 * @property ?CarbonImmutable $failed_at
 * @property ?CarbonImmutable $reconciled_at
 * @property ?int $accepted_late_by_id
 * @property ?CarbonImmutable $accepted_late_at
 * @property ?CarbonImmutable $refunded_at
 * @property ?int $refunded_by_id
 * @property ?string $refund_reason
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Payment extends Model
{
    protected $guarded = ['id', 'valid_key'];

    protected function casts(): array
    {
        return [
            'channel' => PaymentChannel::class,
            'txn_status' => PaymentTxnStatus::class,
            'reconcile_status' => PaymentReconcileStatus::class,
            'is_late' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'succeeded_at' => 'datetime',
            'failed_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'accepted_late_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<PaymentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('id');
    }
}
