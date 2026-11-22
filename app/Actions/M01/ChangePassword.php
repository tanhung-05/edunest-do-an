<?php

namespace App\Actions\M01;

use App\Models\User;
use App\Support\Tx;
use Illuminate\Support\Facades\Hash;

/**
 * ChangeRequiredPassword (A5, CR-109) và ChangeOwnPassword (MH-HV-18): ghi băm mới và xóa cờ
 * "phải đổi mật khẩu" cùng lúc, dưới khóa `users` của người gọi.
 */
final class ChangePassword
{
    public function handle(User $actor, string $newPassword): User
    {
        $me = Tx::run(function () use ($actor, $newPassword) {
            /** @var User $me */
            $me = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $me->password = Hash::make($newPassword);
            $me->must_change_password = false;
            $me->save();

            return $me;
        });
        $actor->setRawAttributes($me->getAttributes(), true);

        return $me;
    }
}
