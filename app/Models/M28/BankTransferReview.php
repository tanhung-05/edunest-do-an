<?php

namespace App\Models\M28;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E34 bank_transfer_reviews — bản X4 chờ tra soát: mã đã liên kết nhưng nội dung không khớp đơn nào (CR-179).
 *
 * @property int $id
 * @property string $bank_code
 * @property ?int $amount
 * @property string $memo_masked
 * @property string $reason
 * @property CarbonImmutable $received_at
 * @property string $status
 */
class BankTransferReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime'];
    }
}
