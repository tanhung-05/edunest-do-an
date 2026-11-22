<?php

namespace App\Jobs\M07;

use App\Enums\M07\InvoicePdfStatus;
use App\Models\M07\Invoice;
use App\Models\M07\Order;
use App\Models\M07\OrderItem;
use App\Support\M12\Commerce;
use App\Support\SimplePdf;
use App\Support\Tx;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * TV-13 (BR-TT-49, CR-61): hóa đơn cho đơn lần đầu `HOAN_TAT`. Số hóa đơn `HD-YYYY-NNNNNN` theo năm VN, duy nhất;
 * cấp dưới khóa dòng đơn, phát lại không tạo hóa đơn thứ hai. Render PDF ngoài giao dịch.
 */
final class GenerateInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $orderId) {}

    public function handle(): void
    {
        $invoice = $this->issue();
        if ($invoice === null || $invoice->pdf_status === InvoicePdfStatus::SanSang) {
            return;
        }
        $order = Order::query()->with(['items', 'user'])->findOrFail($this->orderId);
        $issuedAt = $invoice->issued_at->setTimezone(Commerce::TZ)->format('d/m/Y H:i');
        $note = 'Thanh toán qua cổng mô phỏng. Khóa học số, không hoàn tiền sau khi kích hoạt.';
        $lines = [
            'EduNest - HOA DON DIEN TU',
            'So hoa don: '.$invoice->invoice_number,
            'Ngay xuat: '.$issuedAt,
            'Ma don: '.$order->public_code,
            'Khach hang: '.$order->user->name.' <'.$order->user->email.'>',
            '',
        ];
        $items = [];
        foreach ($order->items as $item) {
            /** @var OrderItem $item */
            $lines[] = '- '.$item->course_title.' ('.$item->kind->label().'): '.Commerce::vnd($item->final_amount);
            $items[] = ['title' => $item->course_title, 'kind' => $item->kind->label(), 'amount' => Commerce::vnd($item->final_amount)];
        }
        $lines[] = '';
        $lines[] = 'Tam tinh: '.Commerce::vnd($order->subtotal);
        $lines[] = 'Giam gia: '.Commerce::vnd($order->discount_total);
        $lines[] = 'Tong thanh toan: '.Commerce::vnd($order->total);
        $lines[] = 'Thanh toan qua cong mo phong (MO_PHONG). Khoa hoc so, khong hoan tien sau khi kich hoat.';

        // CR-209: có Dompdf thì xuất hóa đơn tiếng Việt có dấu; không có thì dùng bản dự phòng chữ không dấu.
        $render = fn (): string => class_exists(Dompdf::class)
            ? Pdf::loadView('pdf.invoice', [
                'number' => $invoice->invoice_number,
                'issuedAt' => $issuedAt,
                'orderCode' => $order->public_code,
                'customer' => $order->user->name,
                'email' => $order->user->email,
                'items' => $items,
                'subtotal' => Commerce::vnd($order->subtotal),
                'discount' => Commerce::vnd($order->discount_total),
                'total' => Commerce::vnd($order->total),
                'note' => $note,
            ])->setOption('isFontSubsettingEnabled', true)->output()
            : SimplePdf::render($lines);

        try {
            $path = 'invoices/'.$invoice->invoice_number.'.pdf';
            Storage::disk('local')->put($path, $render());
            $invoice->update(['pdf_path' => $path, 'pdf_status' => InvoicePdfStatus::SanSang, 'pdf_attempts' => $invoice->pdf_attempts + 1]);
        } catch (Throwable $e) {
            $invoice->update(['pdf_status' => InvoicePdfStatus::Loi, 'pdf_attempts' => $invoice->pdf_attempts + 1]);
            throw $e;
        }
    }

    private function issue(): ?Invoice
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return Tx::run(function () {
                    $order = Order::query()->whereKey($this->orderId)->lockForUpdate()->first();
                    if ($order === null || $order->first_completed_at === null) {
                        return null;
                    }
                    $existing = Invoice::query()->where('order_id', $order->id)->first();
                    if ($existing !== null) {
                        return $existing;
                    }
                    $year = now()->setTimezone(Commerce::TZ)->format('Y');
                    $prefix = 'HD-'.$year.'-';
                    $last = Invoice::query()->where('invoice_number', 'like', $prefix.'%')->orderByDesc('invoice_number')->value('invoice_number');
                    $next = $last === null ? 1 : ((int) substr((string) $last, strlen($prefix))) + 1;

                    return Invoice::query()->create([
                        'order_id' => $order->id, 'invoice_number' => $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                        'issued_at' => now(), 'amount' => $order->total, 'pdf_status' => InvoicePdfStatus::DangTao,
                    ]);
                });
            } catch (QueryException $e) {
                if ($attempt >= 5 || Tx::code($e) !== 1062) {
                    throw $e;
                }
            }
        }
    }
}
