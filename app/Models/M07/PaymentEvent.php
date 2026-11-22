<?php

namespace App\Models\M07;

use App\Enums\M07\PaymentEventOutcome;
use App\Enums\M07\PaymentEventResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L31 payment_events — xác nhận thanh toán (chỉ thêm).
 *
 * @property int $id
 * @property ?int $payment_id
 * @property string $transaction_code
 * @property PaymentEventResult $result
 * @property bool $signature_valid
 * @property string $payload
 * @property ?string $signature
 * @property CarbonImmutable $received_at
 * @property PaymentEventOutcome $outcome
 */
class PaymentEvent extends Model
{
    protected $guarded = ['id', 'accepted_key'];

    protected function casts(): array
    {
        return [
            'result' => PaymentEventResult::class,
            'outcome' => PaymentEventOutcome::class,
            'signature_valid' => 'boolean',
            'received_at' => 'datetime',
        ];
    }

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
