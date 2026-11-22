<?php

namespace App\Support\M15;

/** CR-262: cấu hình VAPID cho thông báo đẩy. Thiếu khóa thì tắt tính năng, giao diện ẩn nút bật. */
final class WebPushConfig
{
    public static function enabled(): bool
    {
        return self::publicKey() !== '' && self::cfg('private_key') !== '';
    }

    public static function publicKey(): string
    {
        return self::cfg('public_key');
    }

    /** @return array{subject: string, publicKey: string, privateKey: string} */
    public static function vapid(): array
    {
        return ['subject' => self::cfg('subject') !== '' ? self::cfg('subject') : 'mailto:no-reply@edunest.test',
            'publicKey' => self::publicKey(), 'privateKey' => self::cfg('private_key')];
    }

    private static function cfg(string $key): string
    {
        $v = config('services.webpush.'.$key);

        return is_string($v) ? $v : '';
    }
}
