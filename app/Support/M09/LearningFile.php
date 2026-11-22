<?php

namespace App\Support\M09;

use App\Models\M01\UserDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

final class LearningFile
{
    public const DEVICE = 'edunest_device';

    public static function hash(string $purpose, string $value): string
    {
        return hash_hmac('sha256', $value, hash_hmac('sha256', $purpose, config()->string('app.key'), true));
    }

    /** @param array<string, int|string> $parameters */
    public static function signed(Request $request, string $route, array $parameters): string
    {
        return URL::temporarySignedRoute($route, now()->addMinutes(15), [...$parameters, ...self::context($request)]);
    }

    public static function verify(Request $request): void
    {
        foreach (self::context($request) as $key => $expected) {
            $given = $request->query($key);
            abort_unless(is_string($given) && hash_equals($expected, $given), 403, 'Liên kết không thuộc phiên hoặc thiết bị này.');
        }
    }

    /** @return array{sid: string, did: string} */
    private static function context(Request $request): array
    {
        $device = $request->attributes->get(self::DEVICE);
        abort_unless($device instanceof UserDevice, 403);

        return [
            'sid' => self::hash('learning-session', $request->session()->getId()),
            'did' => self::hash('learning-device-url', $device->user_id.':'.$device->id.':'.$device->cookie_hash),
        ];
    }

    public static function cookieHash(User $user, string $cookie): string
    {
        return self::hash('learning-device-cookie', $user->id.':'.$cookie);
    }
}
