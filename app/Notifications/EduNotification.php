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
        return ['kind' => $this->kind->value, 'title' => $this->title, 'body' => $this->body, 'url' => self::relative($this->url)];
    }

    /**
     * UAT 22/11: lưu đường dẫn tương đối để thông báo mở đúng dù người dùng vào bằng localhost hay 127.0.0.1.
     * Link ngoài (khác máy chủ ứng dụng) giữ nguyên để bước đọc thông báo từ chối.
     */
    public static function relative(?string $url): ?string
    {
        if ($url === null || $url === '' || str_starts_with($url, '/')) {
            return $url;
        }
        $host = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url(config()->string('app.url'), PHP_URL_HOST);
        if (! is_string($host) || ! in_array($host, array_unique([$appHost, 'localhost', '127.0.0.1']), true)) {
            return $url;
        }
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        return (is_string($path) && $path !== '' ? $path : '/').(is_string($query) ? '?'.$query : '').(is_string($fragment) ? '#'.$fragment : '');
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
