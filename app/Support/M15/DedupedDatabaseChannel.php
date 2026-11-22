<?php

namespace App\Support\M15;

use App\Jobs\M15\SendWebPush;
use App\Models\M15\PushSubscription;
use App\Notifications\EduNotification;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Kênh `notifications` có khóa chống trùng (02b N12): trùng `dedupe_key` thì bỏ qua, không phát lần hai. */
final class DedupedDatabaseChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof EduNotification || ! method_exists($notifiable, 'getKey')) {
            return;
        }
        try {
            DB::table('notifications')->insert([
                'id' => $notification->id ?? (string) Str::uuid(),
                'type' => $notification->kind->value,
                'notifiable_type' => $notifiable::class,
                'notifiable_id' => $notifiable->getKey(),
                'data' => json_encode($notification->toArray($notifiable), JSON_UNESCAPED_UNICODE),
                'dedupe_key' => $notification->dedupeKey === null ? null : mb_substr($notification->dedupeKey, 0, 150),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            // CR-262: bản ghi mới (không trùng) thì đẩy tới trình duyệt đã đăng ký, qua hàng đợi sau khi giao dịch chốt.
            if (WebPushConfig::enabled() && PushSubscription::query()->where('user_id', $notifiable->getKey())->exists()) {
                $data = $notification->toArray($notifiable);
                SendWebPush::dispatch((int) $notifiable->getKey(), ['title' => $data['title'], 'body' => $data['body'], 'url' => $data['url']])->afterCommit();
            }
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
        }
    }
}
