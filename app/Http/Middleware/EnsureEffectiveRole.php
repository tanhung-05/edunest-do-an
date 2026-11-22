<?php

namespace App\Http\Middleware;

use App\Enums\M01\RoleCode;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `role:<CODE,...>` (04 §2): cần ít nhất một vai hiệu lực trong danh sách. Có vai nhân sự đã gán nhưng
 * chưa 2FA → dẫn tới thiết lập 2FA (GET) hoặc 403 (AC-01-20/34); không có vai → 403.
 */
class EnsureEffectiveRole
{
    public function handle(Request $request, Closure $next, string ...$codes): Response
    {
        $user = $request->user();
        $roles = array_map(fn (string $c) => RoleCode::from($c), $codes);
        if (! $user instanceof User) {
            abort(403);
        }
        if ($user->hasEffectiveRole(...$roles)) {
            return $next($request);
        }
        if ($user->isActive() && $user->hasPendingStaffRole(...$roles)) {
            return EnsureStaffTwoFactor::deny($request);
        }
        abort(403, 'Bạn không có quyền truy cập trang này.');
    }
}
