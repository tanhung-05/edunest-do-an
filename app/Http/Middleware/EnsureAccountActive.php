<?php

namespace App\Http\Middleware;

use App\Actions\M01\CreateAuthenticatedSession;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * `account.active` (04 §2, BR-HT-47, CR-04): tài khoản HOAT_DONG và phiên mang đúng thế hệ xác thực.
 * Sai → đăng xuất phiên, 403.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User) {
            $sessionGen = $request->hasSession() ? $request->session()->get(CreateAuthenticatedSession::SESSION_KEY) : null;
            if (! $user->isActive() || $sessionGen === null || (int) $sessionGen !== $user->auth_generation) {
                Auth::guard('web')->logout();
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
                abort(403, $user->isActive() ? 'Phiên đăng nhập đã hết hiệu lực. Hãy đăng nhập lại.' : 'Tài khoản đã bị khóa.');
            }
        }

        return $next($request);
    }
}
