<?php

namespace App\Support\M15;

use App\Enums\M01\RoleCode;
use App\Enums\M15\NotificationKind;
use App\Models\User;
use App\Notifications\EduMail;
use App\Notifications\EduNotification;
use Illuminate\Support\Facades\DB;

/** Điểm gửi thông báo chung: áp tùy chọn tắt theo loại (BR-HT-46), chống trùng, email qua hàng đợi. */
final class Notifier
{
    public static function send(User $user, NotificationKind $kind, string $title, string $body, ?string $url = null, ?string $dedupeKey = null): void
    {
        if ($kind->mutable() && in_array($kind->value, self::mutes($user), true)) {
            return;
        }
        if ($dedupeKey !== null && DB::table('notifications')->where('dedupe_key', mb_substr($dedupeKey, 0, 150))->exists()) {
            return;
        }
        $user->notify(new EduNotification($kind, $title, $body, $url, $dedupeKey));
        if ($kind->emailed()) {
            $user->notify(new EduMail($title, $body, $url));
        }
    }

    public static function academics(NotificationKind $kind, string $title, string $body, ?string $url = null, ?string $dedupePrefix = null): void
    {
        User::query()->whereHas('roles', fn ($q) => $q->where('code', RoleCode::GiaoVu->value))->orderBy('id')
            ->each(fn (User $u) => self::send($u, $kind, $title, $body, $url, $dedupePrefix === null ? null : $dedupePrefix.':'.$u->id));
    }

    /** @return list<string> */
    public static function mutes(User $user): array
    {
        $raw = $user->notification_mutes;

        return is_array($raw) ? array_values(array_filter($raw, 'is_string')) : [];
    }
}
