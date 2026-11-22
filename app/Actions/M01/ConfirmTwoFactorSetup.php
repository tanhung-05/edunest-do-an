<?php

namespace App\Actions\M01;

use App\Models\User;
use App\Support\Tx;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

/**
 * A6 — xác nhận thiết lập 2FA dưới khóa `users` của người gọi; vai nhân sự hiệu lực từ yêu cầu sau.
 */
final class ConfirmTwoFactorSetup extends ConfirmTwoFactorAuthentication
{
    /**
     * @param  mixed  $user
     * @param  string  $code
     */
    public function __invoke($user, $code)
    {
        Tx::run(function () use ($user, $code) {
            /** @var User $me */
            $me = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            parent::__invoke($me, $code);
        });
        $user->refresh();
    }
}
