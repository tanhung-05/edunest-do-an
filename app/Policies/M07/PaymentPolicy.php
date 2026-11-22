<?php

namespace App\Policies\M07;

use App\Enums\M01\RoleCode;
use App\Enums\M07\PaymentChannel;
use App\Models\M07\Payment;
use App\Models\User;

final class PaymentPolicy
{
    public function viewSimulatedGateway(User $user, Payment $payment): bool
    {
        return $payment->channel === PaymentChannel::MoPhong
            && $user->hasEffectiveRole(RoleCode::HocVien) && $payment->order->user_id === $user->id;
    }

    public function sendSimulatedConfirmation(User $user, Payment $payment): bool
    {
        return $this->viewSimulatedGateway($user, $payment);
    }

    public function resendConfirmation(User $user, Payment $payment): bool
    {
        return $this->viewSimulatedGateway($user, $payment);
    }

    public function acceptLate(User $user, Payment $payment): bool
    {
        return $user->hasEffectiveRole(RoleCode::GiaoVu);
    }

    public function markRefunded(User $user, Payment $payment): bool
    {
        return $user->hasEffectiveRole(RoleCode::GiaoVu);
    }
}
