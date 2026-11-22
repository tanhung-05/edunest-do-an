<?php

namespace App\Policies\M07;

use App\Enums\M01\RoleCode;
use App\Models\M07\OrderItem;
use App\Models\User;

final class OrderItemPolicy
{
    public function regrant(User $user, OrderItem $item): bool
    {
        return $user->hasEffectiveRole(RoleCode::GiaoVu);
    }
}
