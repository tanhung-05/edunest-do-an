<?php

namespace App\Models\M13;

use App\Enums\M13\ActivationCodeStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * N08 activation_codes — mã kích hoạt.
 *
 * @property int $id
 * @property int $batch_id
 * @property string $code
 * @property ActivationCodeStatus $status
 * @property ?int $redeemed_by_id
 * @property ?CarbonImmutable $redeemed_at
 * @property ?int $enrollment_id
 * @property ?CarbonImmutable $voided_at
 * @property ?int $voided_by_id
 * @property ?string $void_reason
 */
class ActivationCode extends Model
{
    protected $table = 'activation_codes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ActivationCodeStatus::class,
            'batch_id' => 'integer',
            'redeemed_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ActivationCodeBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ActivationCodeBatch::class, 'batch_id');
    }
}
