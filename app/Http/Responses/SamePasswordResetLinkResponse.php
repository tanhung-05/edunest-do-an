<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/** QT-01 A8 (BR-HT-58, AC-01-40): cùng một phản hồi cho email có hay không có tài khoản. */
class SamePasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public const MESSAGE = 'Nếu email có tài khoản, chúng tôi đã gửi liên kết đặt lại mật khẩu. Liên kết có hiệu lực 60 phút.';

    public function toResponse($request)
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => self::MESSAGE], 200)
            : back()->with('status', self::MESSAGE);
    }
}
