<?php

namespace App\Jobs\M15;

use App\Models\M15\PushSubscription;
use App\Support\M15\WebPushConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * CR-262: gửi thông báo đẩy tới mọi trình duyệt đã đăng ký của một người dùng (chạy ở hàng đợi, không chặn thao tác gốc).
 * Đăng ký hết hạn (404, 410) thì xóa.
 */
final class SendWebPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    /** @param array{title: string, body: string, url: ?string} $payload */
    public function __construct(public int $userId, public array $payload) {}

    public function handle(): void
    {
        $subs = PushSubscription::query()->where('user_id', $this->userId)->get();
        if ($subs->isEmpty() || ! WebPushConfig::enabled()) {
            return;
        }
        try {
            $push = new WebPush(['VAPID' => WebPushConfig::vapid()], ['TTL' => 3600]);
        } catch (\Throwable $e) {
            Log::warning('web-push: chưa khởi tạo được (kiểm tra khóa VAPID, OPENSSL_CONF trên Windows)', ['error' => $e->getMessage()]);

            return;
        }
        $body = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        foreach ($subs as $s) {
            $push->queueNotification(Subscription::create([
                'endpoint' => $s->endpoint, 'publicKey' => $s->p256dh, 'authToken' => $s->auth, 'contentEncoding' => 'aes128gcm',
            ]), $body);
        }
        try {
            foreach ($push->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::query()->where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
                } elseif (! $report->isSuccess()) {
                    Log::info('web-push: gửi không thành công', ['reason' => $report->getReason()]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('web-push: lỗi khi gửi', ['error' => $e->getMessage()]);
        }
    }
}
