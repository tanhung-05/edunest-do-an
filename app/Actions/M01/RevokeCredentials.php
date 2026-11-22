<?php

namespace App\Actions\M01;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Thu hồi mọi phiên/token của tài khoản (TK-3, CR-186). Gọi bên trong giao dịch đã khóa dòng `users`.
 * Tăng `auth_generation` để phiên, cookie ghi nhớ và bước đăng nhập dở mang thế hệ cũ đều mất hiệu lực.
 */
final class RevokeCredentials
{
    public static function apply(User $locked): void
    {
        $locked->auth_generation = $locked->auth_generation + 1;
        $locked->setRememberToken(Str::random(60));
        DB::table('sessions')->where('user_id', $locked->id)->delete();
    }
}
