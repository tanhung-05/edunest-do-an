<?php

namespace App\Support\M07;

use App\Enums\M07\PaymentEventResult;
use App\Models\M07\Payment;
use App\Support\DomainRejection;

final readonly class PaymentConfirmation
{
    /** Miền số thực thu (BIGINT UNSIGNED, giới hạn an toàn cho số nguyên PHP/JS); không dùng trần giá khóa. */
    public const MAX_RECEIVED = 9_007_199_254_740_991;

    public function __construct(public string $code, public PaymentEventResult $result, public ?int $amount) {}

    public static function parse(string $payload): self
    {
        $data = json_decode($payload, true);
        $code = $data['transaction_code'] ?? null;
        $result = $data['result'] ?? null;
        $amount = $data['received_amount'] ?? null;
        $result = is_string($result) ? PaymentEventResult::tryFrom($result) : null;
        if (! is_string($code) || preg_match('/\A[A-Za-z0-9-]{1,40}\z/', $code) !== 1 || $result === null
            || ($result === PaymentEventResult::ThanhCong && (! is_int($amount) || $amount < 0 || $amount > self::MAX_RECEIVED))) {
            throw new DomainRejection('Nội dung xác nhận thanh toán không hợp lệ.');
        }

        return new self($code, $result, $result === PaymentEventResult::ThanhCong ? $amount : null);
    }

    public static function simulated(Payment $payment, PaymentEventResult $result): string
    {
        return json_encode([
            'transaction_code' => $payment->transaction_code,
            'result' => $result->value,
            'received_amount' => $result === PaymentEventResult::ThanhCong ? $payment->requested_amount : null,
        ], JSON_THROW_ON_ERROR);
    }

    public static function sign(string $payload): string
    {
        $secret = config('services.payment_sim.secret');
        if (! is_string($secret) || $secret === '') {
            // Khóa mẫu chỉ dùng ở máy dev/test; môi trường khác thiếu khóa thì từ chối mọi xác nhận.
            if (! app()->environment(['local', 'testing'])) {
                throw new \RuntimeException('Thiếu PAYMENT_SIM_SECRET.');
            }
            $secret = 'edunest-payment-simulation-dev';
        }

        return hash_hmac('sha256', $payload, $secret);
    }
}
