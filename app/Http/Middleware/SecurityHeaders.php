<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * P9.2: tiêu đề bảo mật chung cho mọi phản hồi web. Chặn nhúng khung từ miền khác (clickjacking), không đoán kiểu
 * nội dung, không gửi đường dẫn đầy đủ sang miền khác, tắt quyền thiết bị không dùng. HSTS chỉ khi chạy HTTPS.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()', false);
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        return $response;
    }
}
