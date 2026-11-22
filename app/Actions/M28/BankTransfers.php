<?php

namespace App\Actions\M28;

use App\Actions\M07\ExpireOrder;
use App\Actions\M07\ReconcilePayment;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentChannel;
use App\Enums\M07\PaymentEventOutcome;
use App\Enums\M07\PaymentEventResult;
use App\Enums\M07\PaymentTxnStatus;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use App\Models\M28\BankTransaction;
use App\Models\M28\BankTransferReview;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\Tx;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * QT-30 (02e §6): adapter chuyển khoản. Bước 1–2 mở QR (điều khoản, hạn = hạn đơn); bước 3b nguồn đối soát đã xác thực
 * ghi liên kết mã–khoản–đơn và thứ tự nguồn dưới khóa đơn; bước 4–6 xác nhận HMAC, kiểm liên kết (X3) và nội dung (X4)
 * rồi bàn giao `ReconcilePayment` (bước ghi chung QT-07) với kênh `CHUYEN_KHOAN`.
 */
final class BankTransfers
{
    public function __construct(private ReconcilePayment $reconcile) {}

    /** Nội dung chuyển khoản định danh của đơn: chính mã đơn (khớp chính xác, không phân biệt hoa thường). */
    public static function memo(Order $order): string
    {
        return $order->public_code;
    }

    public static function sign(string $payload): string
    {
        $secret = config('services.bank_sim.secret');
        if (! is_string($secret) || $secret === '') {
            if (! app()->environment(['local', 'testing'])) {
                throw new \RuntimeException('Thiếu BANK_SIM_SECRET.');
            }
            $secret = 'edunest-bank-simulation-dev';
        }

        return hash_hmac('sha256', $payload, $secret);
    }

    /** Bước 1–2: kiểm chủ đơn, `CHO_THANH_TOAN`, còn hạn, xác minh email, đồng ý điều khoản; ghi giờ đồng ý. */
    public function open(User $student, Order $order, bool $accepted): Order
    {
        return Tx::run(function () use ($student, $order, $accepted) {
            $order = OrderLocks::order($order->id);
            $locked = OrderLocks::users($order, $student);
            OrderLocks::student($locked, $order->user_id);
            if (! $locked?->hasVerifiedEmail()) {
                throw DomainRejection::forbidden('Hãy xác minh email trước khi thanh toán.');
            }
            if (app(ExpireOrder::class)->locked($order) || $order->status !== OrderStatus::ChoThanhToan) {
                throw new DomainRejection('Đơn không còn chờ thanh toán.', 'terms');
            }
            if (! $accepted) {
                throw new DomainRejection('Hãy đồng ý điều khoản trước khi chuyển khoản.', 'terms');
            }
            if ($order->transfer_terms_accepted_at === null) {
                $order->forceFill(['transfer_terms_accepted_at' => now()])->save();
            }

            return $order;
        });
    }

    /**
     * Bước 3a–3b: nguồn đối soát báo một giao dịch vào tài khoản. Nhận lại cùng mã dùng liên kết cũ; mã đã gắn đơn mà nội dung
     * trỏ đơn khác thì giữ liên kết gốc. Nội dung không khớp đơn nào: chỉ ghi dấu vết kỹ thuật.
     *
     * @return array{status: 'LINKED'|'EXISTING'|'UNMATCHED', payment_id?: int}
     */
    public function recordSource(string $payload, ?string $signature): array
    {
        if ($signature === null || ! hash_equals(self::sign($payload), $signature)) {
            throw DomainRejection::forbidden('Chữ ký nguồn đối soát không hợp lệ.');
        }
        $data = json_decode($payload, true);
        $code = is_array($data) ? ($data['bank_code'] ?? null) : null;
        $memo = is_array($data) ? ($data['memo'] ?? null) : null;
        $amount = is_array($data) ? ($data['amount'] ?? null) : null;
        $seq = is_array($data) ? ($data['seq'] ?? null) : null;
        if (! is_string($code) || preg_match('/\A[A-Za-z0-9-]{1,40}\z/', $code) !== 1 || ! is_string($memo) || mb_strlen($memo) > 140
            || ! is_int($amount) || $amount < 0 || ! is_int($seq) || $seq < 1) {
            throw new DomainRejection('Dữ liệu nguồn đối soát không hợp lệ.');
        }
        $existing = BankTransaction::query()->where('bank_code', $code)->first();
        if ($existing !== null) {
            return ['status' => 'EXISTING', 'payment_id' => $existing->payment_id];
        }
        $orderId = self::orderIdForMemo($memo);
        if ($orderId === null) {
            Log::info('bank-sim: giao dịch không khớp đơn nào', ['bank_code' => $code]);

            return ['status' => 'UNMATCHED'];
        }

        try {
            return Tx::run(function () use ($code, $memo, $amount, $seq, $orderId) {
                $order = OrderLocks::order($orderId);
                $again = BankTransaction::query()->where('bank_code', $code)->lockForUpdate()->first();
                if ($again !== null) {
                    return ['status' => 'EXISTING', 'payment_id' => $again->payment_id];
                }
                $payment = Payment::query()->create([
                    'order_id' => $order->id, 'transaction_code' => $code, 'requested_amount' => $order->total,
                    'channel' => PaymentChannel::ChuyenKhoan, 'source_seq' => $seq, 'txn_status' => PaymentTxnStatus::KhoiTao,
                    'terms_accepted_at' => $order->transfer_terms_accepted_at ?? now(),
                ]);
                BankTransaction::query()->create([
                    'bank_code' => $code, 'order_id' => $order->id, 'payment_id' => $payment->id, 'source_seq' => $seq,
                    'memo' => mb_substr($memo, 0, 140), 'amount' => $amount, 'received_at' => now(),
                ]);

                return ['status' => 'LINKED', 'payment_id' => $payment->id];
            });
        } catch (QueryException $e) {
            if (Tx::code($e) !== 1062) {
                throw $e;
            }
            $existing = BankTransaction::query()->where('bank_code', $code)->first();
            if ($existing === null) {
                throw new DomainRejection('Thứ tự giao dịch của nguồn bị trùng trong đơn.');
            }

            return ['status' => 'EXISTING', 'payment_id' => $existing->payment_id];
        }
    }

    /**
     * Bước 4–6: X2 chữ ký → X3 mã chưa liên kết → X4 nội dung lệch → bàn giao bước ghi chung.
     *
     * @return array{http: int, outcome: string}
     */
    public function confirm(string $payload, ?string $signature): array
    {
        $valid = $signature !== null && hash_equals(self::sign($payload), $signature);
        if (! $valid) {
            $event = $this->reconcile->handle($payload, $signature, PaymentChannel::ChuyenKhoan, false);

            return ['http' => 403, 'outcome' => $event->outcome->value];
        }
        $data = json_decode($payload, true);
        $code = is_array($data) ? ($data['transaction_code'] ?? null) : null;
        $memo = is_array($data) ? ($data['memo'] ?? null) : null;
        $link = is_string($code) ? BankTransaction::query()->where('bank_code', $code)->first() : null;
        if ($link !== null) {
            $order = Order::query()->findOrFail($link->order_id);
            if (! is_string($memo) || mb_strtoupper(trim($memo)) !== mb_strtoupper(self::memo($order))) {
                $other = is_string($memo) ? self::orderIdForMemo($memo) : null;
                if ($other === null) {
                    // X4: giữ bản chờ tra soát cho Giáo vụ (A4); không đổi khoản, đơn, quyền.
                    BankTransferReview::query()->insertOrIgnore([
                        'bank_code' => $link->bank_code, 'amount' => is_int($data['received_amount'] ?? null) ? $data['received_amount'] : null,
                        'memo_masked' => self::mask(is_string($memo) ? $memo : ''), 'reason' => 'Nội dung chuyển không khớp mã đơn của giao dịch.',
                        'received_at' => now(), 'status' => 'CHO_TRA_SOAT', 'created_at' => now(), 'updated_at' => now(),
                    ]);

                    return ['http' => 409, 'outcome' => 'CHO_TRA_SOAT'];
                }

                // CR-179: nội dung trỏ đơn khác — giữ liên kết gốc, từ chối đối soát cho cả hai đơn, không tạo bản chờ.
                return ['http' => 409, 'outcome' => 'KHONG_KHOP_DON_KHAC'];
            }
        }
        $event = $this->reconcile->handle($payload, $signature, PaymentChannel::ChuyenKhoan, true);

        return ['http' => $event->outcome === PaymentEventOutcome::TuChoiMaLa ? 404 : 200, 'outcome' => $event->outcome->value];
    }

    /**
     * Demo: bộ mô phỏng ngân hàng phát giao dịch (3a) rồi xác nhận (4) cho đơn với số tiền cho trước.
     *
     * @return array{http: int, outcome: string}
     */
    public function simulate(Order $order, int $amount, PaymentEventResult $result = PaymentEventResult::ThanhCong): array
    {
        $code = 'NH-'.Str::upper(Str::random(12));
        $seq = 1 + (int) Payment::query()->where('order_id', $order->id)->where('channel', PaymentChannel::ChuyenKhoan->value)->max('source_seq');
        $source = json_encode(['bank_code' => $code, 'memo' => self::memo($order), 'amount' => $amount, 'seq' => $seq], JSON_THROW_ON_ERROR);
        $this->recordSource($source, self::sign($source));
        $confirmation = json_encode(['transaction_code' => $code, 'result' => $result->value, 'memo' => self::memo($order),
            'received_amount' => $result === PaymentEventResult::ThanhCong ? $amount : null], JSON_THROW_ON_ERROR);

        return $this->confirm($confirmation, self::sign($confirmation));
    }

    /**
     * Thông tin QR cho trang đơn: chỉ khi đơn còn chờ và đã đồng ý điều khoản chuyển khoản.
     *
     * @return ?array{bank: string, account_number: string, account_name: string, amount: int, memo: string, expires_at: string, qr_svg: string} */
    public static function view(Order $order): ?array
    {
        if ($order->status !== OrderStatus::ChoThanhToan || $order->transfer_terms_accepted_at === null || now()->gt($order->expires_at)) {
            return null;
        }
        $bank = (string) config('services.bank_sim.bank');
        $number = (string) config('services.bank_sim.account_number');
        $name = (string) config('services.bank_sim.account_name');

        return [
            'bank' => $bank, 'account_number' => $number, 'account_name' => $name, 'amount' => $order->total,
            'memo' => self::memo($order), 'expires_at' => $order->expires_at->toIso8601String(),
            'qr_svg' => (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))
                ->writeString('EDUNEST|'.$number.'|'.$order->total.'|'.self::memo($order)),
        ];
    }

    private static function orderIdForMemo(string $memo): ?int
    {
        $id = Order::query()->where('public_code', mb_strtoupper(trim($memo)))->value('id');

        return $id === null ? null : (int) $id;
    }

    /** CR-179: che dữ liệu cá nhân trong nội dung chuyển (email, dãy số dài như số điện thoại, số tài khoản). */
    public static function mask(string $memo): string
    {
        $memo = (string) preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', '***@***', $memo);

        return mb_substr((string) preg_replace('/\d{6,}/', '******', $memo), 0, 140);
    }
}
