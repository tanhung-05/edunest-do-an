<?php

namespace App\Support\M07;

use App\Enums\M07\PaymentEventResult;
use App\Models\M07\Payment;
use App\Support\DomainRejection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * P10 (CR-260): ví MoMo ở môi trường thử (test-payment.momo.vn), không tiền thật.
 * Tạo giao dịch "captureWallet" rồi chuyển học viên sang trang MoMo; kết quả về qua trang quay lại và IPN,
 * cả hai đều kiểm chữ ký HMAC-SHA256 bằng secret key rồi chuyển thành xác nhận chung của EduNest (ReconcilePayment).
 * Trống khóa thì kênh này tắt, học viên chỉ thấy cổng mô phỏng.
 */
final class MoMoGateway
{
    /** Trường của chữ ký kết quả (trang quay lại, IPN), theo thứ tự chữ cái như tài liệu MoMo. */
    private const RESULT_FIELDS = ['accessKey', 'amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType',
        'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId'];

    public static function enabled(): bool
    {
        return self::cfg('partner_code') !== '' && self::cfg('access_key') !== '' && self::cfg('secret_key') !== '';
    }

    /** Tạo giao dịch ở MoMo, trả địa chỉ trang thanh toán. */
    public static function payUrl(Payment $payment): string
    {
        $fields = [
            'accessKey' => self::cfg('access_key'),
            'amount' => (string) $payment->requested_amount,
            'extraData' => '',
            'ipnUrl' => route('webhooks.momo'),
            'orderId' => $payment->transaction_code,
            'orderInfo' => 'Thanh toan don '.$payment->order->public_code,
            'partnerCode' => self::cfg('partner_code'),
            'redirectUrl' => route('payments.momo.return'),
            'requestId' => $payment->transaction_code,
            'requestType' => 'captureWallet',
        ];
        $body = [...$fields, 'lang' => 'vi', 'signature' => self::sign($fields)];
        unset($body['accessKey']);

        try {
            $res = Http::timeout(20)->acceptJson()->post(self::cfg('endpoint').'/v2/gateway/api/create', $body);
        } catch (\Throwable $e) {
            Log::warning('momo: không gọi được cổng', ['error' => $e->getMessage()]);
            throw new DomainRejection('Chưa kết nối được MoMo, hãy thử lại hoặc chọn cách thanh toán khác.');
        }
        $url = $res->json('payUrl');
        if (! $res->successful() || (int) $res->json('resultCode', -1) !== 0 || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            Log::warning('momo: tạo giao dịch bị từ chối', ['status' => $res->status(), 'resultCode' => $res->json('resultCode'), 'message' => $res->json('message')]);
            throw new DomainRejection('MoMo từ chối tạo giao dịch: '.(is_string($res->json('message')) ? $res->json('message') : 'lỗi không rõ').'.');
        }

        return $url;
    }

    /**
     * Kiểm chữ ký kết quả MoMo gửi về.
     *
     * @param  array<string, mixed>  $data
     */
    public static function verify(array $data): bool
    {
        $signature = $data['signature'] ?? null;
        if (! is_string($signature) || self::cfg('secret_key') === '' || ($data['partnerCode'] ?? null) !== self::cfg('partner_code')) {
            return false;
        }
        $fields = [];
        foreach (self::RESULT_FIELDS as $k) {
            $fields[$k] = $k === 'accessKey' ? self::cfg('access_key') : (string) (is_scalar($data[$k] ?? null) ? $data[$k] : '');
        }

        return hash_equals(self::sign($fields), $signature);
    }

    /**
     * Đổi kết quả MoMo sang nội dung xác nhận chung (transaction_code, result, received_amount).
     *
     * @param  array<string, mixed>  $data
     */
    public static function confirmation(array $data): string
    {
        $ok = (string) ($data['resultCode'] ?? '') === '0';

        return json_encode([
            'transaction_code' => (string) ($data['orderId'] ?? ''),
            'result' => ($ok ? PaymentEventResult::ThanhCong : PaymentEventResult::ThatBai)->value,
            'received_amount' => $ok ? (int) ($data['amount'] ?? 0) : null,
        ], JSON_THROW_ON_ERROR);
    }

    /** @param array<string, string> $fields */
    public static function sign(array $fields): string
    {
        ksort($fields);
        $raw = implode('&', array_map(fn ($k, $v) => $k.'='.$v, array_keys($fields), $fields));

        return hash_hmac('sha256', $raw, self::cfg('secret_key'));
    }

    private static function cfg(string $key): string
    {
        $v = config('services.momo.'.$key);

        return is_string($v) ? $v : '';
    }
}
