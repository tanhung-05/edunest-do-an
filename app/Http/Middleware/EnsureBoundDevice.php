<?php

namespace App\Http\Middleware;

use App\Enums\M01\RoleCode;
use App\Http\Controllers\Auth\DeviceLimitController;
use App\Models\M01\UserDevice;
use App\Models\User;
use App\Support\M09\LearningFile;
use App\Support\M16\DeviceRegistry;
use App\Support\Tx;
use Closure;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class EnsureBoundDevice
{
    public function onLogin(Login $event): void
    {
        $request = app(Request::class);
        $user = $event->user;
        if (! $request->hasSession() || ! $user instanceof User || ! $user->hasEffectiveRole(RoleCode::HocVien)) {
            return;
        }

        $request->session()->forget(LearningFile::DEVICE);
        $admitted = $request->session()->pull(DeviceLimitController::ADMITTED);
        // QT-17 b2, CR-114: gắn phiên với thiết bị dùng lúc đăng nhập, không đợi yêu cầu học đầu tiên.
        // Thiết bị đã được nhận dưới khóa `users` ở bước xác thực (DeviceRegistry::admit); đăng nhập bằng cookie
        // ghi nhớ chỉ thành công khi thiết bị của cookie còn hiệu lực.
        $device = is_numeric($admitted) ? UserDevice::query()->where('user_id', $user->id)->find((int) $admitted) : null;
        $device ??= UserDevice::query()->firstOrCreate(
            ['user_id' => $user->id, 'cookie_hash' => LearningFile::cookieHash($user, DeviceRegistry::cookie($request))],
            ['first_seen_at' => now(), 'last_active_at' => now()],
        );
        if ($device->revoked_at === null) {
            $request->session()->put(LearningFile::DEVICE, $device->id);
        }
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $session = $request->session();
        $storedId = config('session.driver') === 'database'
            ? DB::table(config()->string('session.table'))->where('id', $session->getId())->value('device_id') : null;
        $id = $storedId ?? $session->get(LearningFile::DEVICE);
        $cookie = $request->cookie(LearningFile::DEVICE);

        if (is_numeric($id)) {
            $device = UserDevice::query()->where('user_id', $user->id)->whereNull('revoked_at')->find((int) $id);
            if ($device === null || ! is_string($cookie) || ! hash_equals($device->cookie_hash, LearningFile::cookieHash($user, $cookie))) {
                Auth::guard('web')->logout();
                $session->invalidate();
                $session->regenerateToken();

                return response('Phiên không còn gắn với thiết bị này.', 403);
            }
        } else {
            abort_if($request->query->has('sid') || $request->query->has('did'), 403);
            $cookie = is_string($cookie) && self::validCookie($cookie) ? $cookie : Str::random(64);
            $device = Tx::run(function () use ($user, $cookie): UserDevice {
                $current = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                abort_unless($current->isActive() && $current->auth_generation === $user->auth_generation, 403);

                return UserDevice::query()->firstOrCreate(
                    ['user_id' => $current->id, 'cookie_hash' => LearningFile::cookieHash($current, $cookie)],
                    ['first_seen_at' => now(), 'last_active_at' => now()],
                );
            });
            abort_if($device->revoked_at !== null, 403);
            self::queueCookie($request, $cookie);
        }

        $device->last_active_at = now();
        $device->save();
        $session->put(LearningFile::DEVICE, $device->id);
        if (config('session.driver') === 'database' && $storedId === null) {
            DB::table(config()->string('session.table'))->where('id', $session->getId())
                ->whereNull('device_id')->update(['device_id' => $device->id]);
        }
        $request->attributes->set(LearningFile::DEVICE, $device);

        return $next($request);
    }

    private static function validCookie(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9]{64}$/D', $value) === 1;
    }

    private static function queueCookie(Request $request, string $cookie): void
    {
        Cookie::queue(Cookie::make(LearningFile::DEVICE, $cookie, 525600, '/', null, $request->isSecure(), true, false, 'lax'));
    }
}
