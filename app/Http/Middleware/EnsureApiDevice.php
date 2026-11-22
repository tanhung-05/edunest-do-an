<?php

namespace App\Http\Middleware;

use App\Enums\M01\RoleCode;
use App\Models\M01\UserDevice;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * M28 + M16: yêu cầu API phải mang token gắn thiết bị chưa thu hồi, tài khoản đang hoạt động, vai Học viên hiệu lực.
 * Cập nhật hoạt động của thiết bị tối đa mỗi 5 phút.
 */
final class EnsureApiDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;
        if (! $user instanceof User || ! $token instanceof PersonalAccessToken) {
            return response()->json(['message' => 'Cần token API.'], 401);
        }
        $deviceId = $token->getAttribute('user_device_id');
        $device = is_int($deviceId) ? UserDevice::query()->whereKey($deviceId)->first() : null;
        if ($device === null || $device->revoked_at !== null || $device->user_id !== $user->id) {
            $token->delete();

            return response()->json(['message' => 'Thiết bị đã bị đăng xuất.'], 401);
        }
        if (! $user->isActive() || ! $user->hasEffectiveRole(RoleCode::HocVien)) {
            return response()->json(['message' => 'Tài khoản không dùng được API.'], 403);
        }
        if ($device->last_active_at->lt(now()->subMinutes(5))) {
            $device->forceFill(['last_active_at' => now()])->save();
        }

        return $next($request);
    }
}
