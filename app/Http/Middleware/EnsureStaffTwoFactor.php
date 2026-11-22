<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `staff.2fa` (04 §2, QT-01 A6/A14, CR-109): tài khoản chỉ có vai Giáo vụ/Quản trị mà chưa xác nhận 2FA
 * thì mọi màn dẫn về thiết lập 2FA. Tài khoản còn vai khác (ví dụ Giáo viên được gán thêm Giáo vụ)
 * vẫn dùng quyền cũ; tuyến cần vai nhân sự do `role:` xử lý.
 */
class EnsureStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->onlyPendingStaffRoles()) {
            return self::deny($request);
        }

        return $next($request);
    }

    public static function deny(Request $request): Response
    {
        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            return redirect()->route('account.security.edit')
                ->with('status', 'Hãy thiết lập xác thực hai lớp để dùng quyền Giáo vụ/Quản trị.');
        }
        abort(403, 'Cần thiết lập xác thực hai lớp trước.');
    }
}
