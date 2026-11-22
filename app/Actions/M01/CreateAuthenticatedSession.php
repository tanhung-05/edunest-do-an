<?php

namespace App\Actions\M01;

use App\Models\User;
use App\Support\TestBarrier;
use App\Support\Tx;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Session\Session;

/**
 * 03 TK-3 / DT-26 — ghi phiên dưới khóa `users`: đọc lại trạng thái và thế hệ xác thực rồi gắn
 * thế hệ vào phiên. Bước đăng nhập dở (chờ mã 2FA) mang thế hệ lúc nhập mật khẩu (`login.gen`);
 * nếu tài khoản đã bị khóa (kể cả đã mở lại) trong lúc đó thì phiên không hợp lệ.
 * Middleware `account.active` so thế hệ của phiên với dòng `users` ở mỗi yêu cầu.
 */
final class CreateAuthenticatedSession
{
    public const SESSION_KEY = 'auth.gen';

    public const PENDING_KEY = 'login.gen';

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || ! app()->bound('session.store')) {
            return;
        }
        /** @var Session $session */
        $session = app('session.store');
        $pending = $session->pull(self::PENDING_KEY);

        $generation = Tx::run(function () use ($event, $pending) {
            /** @var User $u */
            $u = User::query()->whereKey($event->user->getAuthIdentifier())->lockForUpdate()->firstOrFail();
            TestBarrier::reach('create-session.locked');
            if (! $u->isActive()) {
                return -1;
            }
            if ($pending !== null && (int) $pending !== $u->auth_generation) {
                return -1;
            }

            return $u->auth_generation;
        });

        $session->put(self::SESSION_KEY, $generation);
    }
}
