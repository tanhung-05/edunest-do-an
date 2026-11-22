<?php

namespace App\Actions\M29;

use App\Enums\M01\RoleCode;
use App\Events\M29\SupportMessagePosted;
use App\Models\M29\SupportMessage;
use App\Models\M29\SupportThread;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\Tx;

/**
 * P10 M29 (CR-256): chat trực tiếp với nhân viên. Người dùng đã đăng nhập (không phải nhân sự hỗ trợ) nhắn vào cuộc trò
 * chuyện của mình; Giáo vụ trả lời, đóng. Mọi ghi đều khóa dòng cuộc trò chuyện để đếm chưa đọc và trạng thái không lệch
 * khi hai bên nhắn cùng lúc. Gửi lặp cùng khóa yêu cầu trả lại tin đã ghi, không tạo tin mới.
 */
final class SupportChat
{
    public const MAX_BODY = 2000;

    public static function isAgent(User $user): bool
    {
        return $user->isActive() && $user->hasEffectiveRole(RoleCode::GiaoVu);
    }

    /** Chủ cuộc trò chuyện hoặc nhân sự hỗ trợ được xem (kiểm cả khi đăng ký kênh phát). Khách không có kênh riêng, dùng thăm dò. */
    public static function canView(User $user, SupportThread $thread): bool
    {
        return ($thread->user_id !== null && $thread->user_id === $user->id) || self::isAgent($user);
    }

    /**
     * Người dùng đã đăng nhập (theo tài khoản) hoặc Khách (theo khóa phiên, CR-258) nhắn nhân viên. Khách phải có họ tên và
     * số điện thoại ở tin đầu tiên; tin sau được đổi lại nếu Khách sửa.
     */
    public function send(?User $user, ?string $guestKey, string $body, string $requestKey, ?string $guestName = null, ?string $guestPhone = null): SupportMessage
    {
        $body = self::body($body);
        self::requestKey($requestKey);
        if ($user === null) {
            if ($guestKey === null || preg_match('/^[a-f0-9]{64}$/', $guestKey) !== 1) {
                throw DomainRejection::forbidden('Phiên trò chuyện không hợp lệ.');
            }
            [$guestName, $guestPhone] = self::contact($guestName, $guestPhone);
        }

        [$message, $thread, $created] = Tx::run(function () use ($user, $guestKey, $body, $requestKey, $guestName, $guestPhone) {
            $owner = ['user_id' => null, 'guest_key' => $guestKey];
            if ($user !== null) {
                $locked = User::query()->whereKey($user->id)->sharedLock()->firstOrFail();
                if (! $locked->isActive()) {
                    throw DomainRejection::forbidden('Tài khoản không còn hoạt động.');
                }
                if (self::isAgent($locked)) {
                    throw DomainRejection::forbidden('Nhân sự hỗ trợ trả lời ở hộp thư hỗ trợ.');
                }
                $owner = ['user_id' => $locked->id, 'guest_key' => null];
            }
            $existing = SupportMessage::query()->where('request_key', $requestKey)->first();
            if ($existing !== null) {
                $thread = SupportThread::query()->findOrFail($existing->support_thread_id);
                if ($thread->user_id !== $owner['user_id'] || $thread->guest_key !== $owner['guest_key']) {
                    throw DomainRejection::forbidden('Khóa yêu cầu không hợp lệ.');
                }

                return [$existing, $thread, false];
            }
            $key = $owner['user_id'] !== null ? ['user_id' => $owner['user_id']] : ['guest_key' => $owner['guest_key']];
            SupportThread::query()->insertOrIgnore([...$key, 'status' => SupportThread::CHO, 'created_at' => now(), 'updated_at' => now()]);
            $thread = SupportThread::query()->where($key)->lockForUpdate()->firstOrFail();
            $message = SupportMessage::query()->create([
                'support_thread_id' => $thread->id, 'sender_id' => $owner['user_id'], 'from_staff' => false,
                'body' => $body, 'request_key' => $requestKey, 'created_at' => now(),
            ]);
            $thread->forceFill([
                'status' => SupportThread::CHO, 'staff_unread' => $thread->staff_unread + 1, 'last_message_at' => $message->created_at,
                ...($owner['user_id'] === null ? ['guest_name' => $guestName, 'guest_phone' => $guestPhone] : []),
            ])->save();

            return [$message, $thread, true];
        });

        if ($created) {
            SupportMessagePosted::dispatch($thread->id, $message->load('sender:id,name')->toChat($thread->guest_name), $thread->status);
        }

        return $message;
    }

    /**
     * Họ tên 2–80 ký tự; số điện thoại Việt Nam 10 số (0xxxxxxxxx hoặc +84xxxxxxxxx), lưu dạng 0xxxxxxxxx.
     *
     * @return array{0: string, 1: string}
     */
    public static function contact(?string $name, ?string $phone): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            throw new DomainRejection('Bạn nhập họ tên (2 đến 80 ký tự) để nhân viên tiện xưng hô.', 'guest_name');
        }
        $digits = (string) preg_replace('/[\s.\-()]/', '', (string) $phone);
        if (str_starts_with($digits, '+84')) {
            $digits = '0'.substr($digits, 3);
        }
        if (preg_match('/^0[35789]\d{8}$/', $digits) !== 1) {
            throw new DomainRejection('Số điện thoại chưa đúng (10 số, ví dụ 0912345678).', 'guest_phone');
        }

        return [$name, $digits];
    }

    public function reply(User $agent, SupportThread $thread, string $body, string $requestKey): SupportMessage
    {
        $body = self::body($body);
        self::requestKey($requestKey);

        [$message, $thread, $created] = Tx::run(function () use ($agent, $thread, $body, $requestKey) {
            $locked = User::query()->whereKey($agent->id)->sharedLock()->firstOrFail();
            if (! self::isAgent($locked)) {
                throw DomainRejection::forbidden('Chỉ Giáo vụ trả lời chat hỗ trợ.');
            }
            $thread = SupportThread::query()->whereKey($thread->id)->lockForUpdate()->firstOrFail();
            $existing = SupportMessage::query()->where('request_key', $requestKey)->first();
            if ($existing !== null) {
                if ($existing->support_thread_id !== $thread->id || ! $existing->from_staff) {
                    throw DomainRejection::forbidden('Khóa yêu cầu không hợp lệ.');
                }

                return [$existing, $thread, false];
            }
            $message = SupportMessage::query()->create([
                'support_thread_id' => $thread->id, 'sender_id' => $locked->id, 'from_staff' => true,
                'body' => $body, 'request_key' => $requestKey, 'created_at' => now(),
            ]);
            $thread->forceFill([
                'status' => SupportThread::DA_TRA_LOI, 'assigned_to_id' => $thread->assigned_to_id ?? $locked->id,
                'user_unread' => $thread->user_unread + 1, 'staff_unread' => 0, 'last_message_at' => $message->created_at,
            ])->save();

            return [$message, $thread, true];
        });

        if ($created) {
            SupportMessagePosted::dispatch($thread->id, $message->load('sender:id,name')->toChat($thread->guest_name), $thread->status);
        }

        return $message;
    }

    public function close(User $agent, SupportThread $thread): SupportThread
    {
        return Tx::run(function () use ($agent, $thread) {
            $locked = User::query()->whereKey($agent->id)->sharedLock()->firstOrFail();
            if (! self::isAgent($locked)) {
                throw DomainRejection::forbidden('Chỉ Giáo vụ đóng chat hỗ trợ.');
            }
            $thread = SupportThread::query()->whereKey($thread->id)->lockForUpdate()->firstOrFail();
            if ($thread->status !== SupportThread::DONG) {
                $old = $thread->status;
                $thread->forceFill(['status' => SupportThread::DONG, 'staff_unread' => 0])->save();
                Audit::log('SUPPORT_THREAD_CLOSED', $locked, $thread, ['status' => $old], ['status' => SupportThread::DONG]);
            }

            return $thread;
        });
    }

    /** Đánh dấu phía người xem đã đọc hết (Khách: `$viewer` null và đúng khóa phiên). */
    public function markRead(?User $viewer, SupportThread $thread, ?string $guestKey = null): void
    {
        $column = match (true) {
            $viewer === null => $guestKey !== null && $thread->guest_key === $guestKey ? 'user_unread' : null,
            $thread->user_id === $viewer->id => 'user_unread',
            self::isAgent($viewer) => 'staff_unread',
            default => null,
        };
        if ($column === null || $thread->{$column} === 0) {
            return;
        }
        SupportThread::query()->whereKey($thread->id)->update([$column => 0]);
    }

    private static function body(string $body): string
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > self::MAX_BODY) {
            throw new DomainRejection('Tin nhắn cần từ 1 đến '.self::MAX_BODY.' ký tự.', 'body');
        }

        return $body;
    }

    private static function requestKey(string $key): void
    {
        if (preg_match('/^[A-Za-z0-9-]{8,64}$/', $key) !== 1) {
            throw new DomainRejection('Khóa yêu cầu không hợp lệ.', 'body');
        }
    }
}
