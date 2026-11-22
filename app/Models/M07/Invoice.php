<?php

namespace App\Models\M07;

use App\Enums\M07\InvoicePdfStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * N10 invoices — hóa đơn điện tử (BR-TT-49).
 *
 * @property int $id
 * @property int $order_id
 * @property string $invoice_number
 * @property CarbonImmutable $issued_at
 * @property int $amount
 * @property ?string $pdf_path
 * @property InvoicePdfStatus $pdf_status
 * @property int $pdf_attempts
 */
class Invoice extends Model
{
    protected $table = 'invoices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pdf_status' => InvoicePdfStatus::class,
            'order_id' => 'integer',
            'amount' => 'integer',
            'pdf_attempts' => 'integer',
            'issued_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
