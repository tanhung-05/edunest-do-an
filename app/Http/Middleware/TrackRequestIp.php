<?php

namespace App\Http\Middleware;

use App\Enums\M15\NotificationKind;
use App\Models\M16\SecurityAlert;
use App\Models\User;
use App\Support\M15\Notifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * QT-17 b10, BR-TB-25, CR-137: đếm IP của mọi yêu cầu đã xác thực trong 10 phút trượt (mỗi IP giữ giờ gần nhất);
 * đủ 3 IP thì cảnh báo Giáo vụ, không phát lặp cho cùng tài khoản trong 10 phút. Không khóa tài khoản.
 */
final class TrackRequestIp
{
    public const WINDOW = 600;

    public const THRESHOLD = 3;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();
        $ip = $request->ip();
        if ($user instanceof User && is_string($ip)) {
            self::record($user, $ip, now()->getTimestamp());
        }

        return $response;
    }

    public static function record(User $user, string $ip, int $now): void
    {
        Cache::lock('ipwin-lock:'.$user->id, 5)->block(3, function () use ($user, $ip, $now) {
            $key = 'ipwin:'.$user->id;
            /** @var array<string, int> $window */
            $window = Cache::get($key, []);
            $window[$ip] = $now;
            $window = array_filter($window, fn (int $at) => $at > $now - self::WINDOW);
            Cache::put($key, $window, self::WINDOW);
            if (count($window) < self::THRESHOLD || Cache::has('ipalert:'.$user->id)) {
                return;
            }
            Cache::put('ipalert:'.$user->id, $now, self::WINDOW);
            $alert = SecurityAlert::query()->create(['user_id' => $user->id, 'ip_list' => array_keys($window), 'detected_at' => now()]);
            Notifier::academics(NotificationKind::CanhBaoBatThuong, 'Cảnh báo đăng nhập bất thường',
                'Tài khoản '.$user->email.' dùng '.count($window).' địa chỉ IP trong 10 phút.', route('academic.moderation.index'), 'ip-alert:'.$alert->id);
        });
    }
}
