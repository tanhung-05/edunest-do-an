<?php

namespace App\Notifications;

use App\Enums\M15\NotificationKind;
use App\Support\M15\DedupedDatabaseChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Thông báo chuông (M15). Ghi `notifications` qua kênh chống trùng theo `dedupe_key`, phát Reverb khi bật.
 * Email gửi riêng qua `EduMail` (hàng đợi) để không chặn thao tác gốc (BR-HT-45).
 */
final class EduNotification extends Notification
{
    public function __construct(
        public readonly NotificationKind $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly ?string $dedupeKey = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return config('broadcasting.default') === 'reverb'
            ? [DedupedDatabaseChannel::class, 'broadcast'] : [DedupedDatabaseChannel::class];
    }

    /** @return array{kind: string, title: string, body: string, url: ?string} */
    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind->value, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
