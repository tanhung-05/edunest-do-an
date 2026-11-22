<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `password.changed` (04 §2, QT-01 A5): tài khoản được cấp phải đổi mật khẩu trước khi vào màn khác. */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->must_change_password) {
            if ($request->expectsJson()) {
                abort(403, 'Bạn cần đổi mật khẩu trước.');
            }

            return redirect()->route('account.password.required');
        }

        return $next($request);
    }
}
