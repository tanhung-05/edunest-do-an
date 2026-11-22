<?php

namespace App\Support\M16;

use App\Enums\M01\RoleCode;
use App\Models\M01\UserDevice;
use App\Models\M11\Setting;
use App\Models\User;
use App\Support\M09\LearningFile;
use App\Support\TestBarrier;
use App\Support\Tx;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * QT-17 b2–5, b12–13, A1–A10, E20/E21 (TB-1, TB-2). Thiết bị = cặp (tài khoản, cookie). Kiểm và ghi thiết bị mới
 * dưới khóa dòng `users`, nên hai đăng nhập đồng thời không vượt giới hạn (DT-42).
 */
final class DeviceRegistry
{
    public const ACTIVE_DAYS = 30;

    private const FRESH = 'edunest_device_fresh';

    /** Cookie hợp lệ của trình duyệt, hoặc cookie mới (cấp một lần cho cả yêu cầu). */
    public static function cookie(Request $request, bool $renew = false): string
    {
        $fresh = $request->attributes->get(self::FRESH);
        if (is_string($fresh) && ! $renew) {
            return $fresh;
        }
        $cookie = $request->cookie(LearningFile::DEVICE);
        if ($renew || ! is_string($cookie) || preg_match('/^[A-Za-z0-9]{64}$/D', $cookie) !== 1) {
            $cookie = Str::random(64);
            $request->attributes->set(self::FRESH, $cookie);
            Cookie::queue(Cookie::make(LearningFile::DEVICE, $cookie, 525600, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return $cookie;
    }

    /** Cookie đang có (kể cả cookie vừa cấp trong yêu cầu này), không cấp mới. */
    public static function cookieIfPresent(Request $request): ?string
    {
        $fresh = $request->attributes->get(self::FRESH);
        if (is_string($fresh)) {
            return $fresh;
        }
        $cookie = $request->cookie(LearningFile::DEVICE);

        return is_string($cookie) && preg_match('/^[A-Za-z0-9]{64}$/D', $cookie) === 1 ? $cookie : null;
    }

    /** Cấp token ghi nhớ riêng cho thiết bị (BR-TB-18). */
    public static function issueRemember(User $user, UserDevice $device): void
    {
        $token = Str::random(60);
        $device->update(['remember_token_hash' => hash('sha256', $token)]);
        $user->useDeviceRememberToken($token);
    }

    /** Học viên chịu giới hạn (kể cả kiêm vai nhân sự, A7); tài khoản chỉ có vai nhân sự không giới hạn (A3). */
    public static function limited(User $user): bool
    {
        return in_array(RoleCode::HocVien, $user->roleCodes(), true);
    }

    /** @return Collection<int, UserDevice> */
    public static function active(User $user): Collection
    {
        return UserDevice::query()->where('user_id', $user->id)->whereNull('revoked_at')
            ->where('last_active_at', '>=', now()->subDays(self::ACTIVE_DAYS))->orderByDesc('last_active_at')->get();
    }

    /**
     * TB-1: nhận thiết bị cho lần đăng nhập. Trả null khi đã đủ giới hạn (E20, phải chọn thiết bị cũ).
     * `$replace` (A1): thiết bị cũ người dùng chọn đăng xuất trong cùng giao dịch.
     */
    public static function admit(Request $request, User $user, ?int $replace = null): ?UserDevice
    {
        return Tx::run(function () use ($request, $user, $replace) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            TestBarrier::reach('admit-device-locked');
            if ($replace !== null) {
                $old = UserDevice::query()->where('user_id', $locked->id)->whereKey($replace)->first();
                if ($old !== null && $old->revoked_at === null) {
                    self::revokeLocked($old);
                }
            }
            $now = now();
            $cookie = self::cookie($request);
            $device = UserDevice::query()->where('user_id', $locked->id)->where('cookie_hash', LearningFile::cookieHash($locked, $cookie))->first();
            $isActive = $device !== null && $device->revoked_at === null && $device->last_active_at->gte($now->subDays(self::ACTIVE_DAYS));
            if (! $isActive && $device !== null && $device->revoked_at !== null) {
                // CR-136: cookie của thiết bị đã thu hồi đi như thiết bị mới với cookie mới.
                $cookie = self::cookie($request, true);
                $device = null;
            }
            $meta = ['user_agent' => mb_substr((string) $request->userAgent(), 0, 255), 'last_ip' => $request->ip(), ...self::describe((string) $request->userAgent())];
            if ($isActive) {
                $device->update(['last_active_at' => $now, ...$meta]);

                return $device;
            }
            if (self::limited($locked) && self::active($locked)->count() >= Setting::number('device.max_active')) {
                return null;
            }
            if ($device !== null) {
                $device->update(['last_active_at' => $now, ...$meta]);

                return $device;
            }

            return UserDevice::query()->create([
                'user_id' => $locked->id, 'cookie_hash' => LearningFile::cookieHash($locked, $cookie),
                'first_seen_at' => $now, 'last_active_at' => $now, ...$meta,
            ]);
        });
    }

    /** TB-2 (NH-23): đăng xuất từ xa. Trả false khi thiết bị đã bị thu hồi trước đó (không thu hồi hai lần). */
    public static function revoke(User $actor, int $deviceId): bool
    {
        return Tx::run(function () use ($actor, $deviceId) {
            $locked = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $device = UserDevice::query()->where('user_id', $locked->id)->whereKey($deviceId)->lockForUpdate()->first();
            abort_if($device === null, 404);
            if ($device->revoked_at !== null) {
                return false;
            }
            self::revokeLocked($device);

            return true;
        });
    }

    /** Thu hồi phiên web, token ghi nhớ của thiết bị; giữ bản ghi với dấu đã thu hồi (CR-136). */
    private static function revokeLocked(UserDevice $device): void
    {
        $device->update(['revoked_at' => now(), 'remember_token_hash' => null]);
        DB::table(config()->string('session.table', 'sessions'))->where('device_id', $device->id)->delete();
        // P8 M28: token API Sanctum gắn thiết bị bị thu hồi cùng lúc (M16).
        DB::table('personal_access_tokens')->where('user_device_id', $device->id)->delete();
    }

    /** @return array{browser: string, os: string} */
    public static function describe(string $agent): array
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Microsoft Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Trình duyệt khác',
        };
        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Không rõ',
        };

        return ['browser' => $browser, 'os' => $os];
    }
}
