<?php

namespace App\Models\M28;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E33 bank_transactions — liên kết mã giao dịch ngân hàng–khoản–đơn và thứ tự nguồn, ghi ở QT-30 bước 3b (CR-178).
 *
 * @property int $id
 * @property string $bank_code
 * @property int $order_id
 * @property int $payment_id
 * @property int $source_seq
 * @property string $memo
 * @property int $amount
 * @property CarbonImmutable $received_at
 */
class BankTransaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime'];
    }
}
