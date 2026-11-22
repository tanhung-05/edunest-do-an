<?php

namespace App\Policies\M07;

use App\Enums\M01\RoleCode;
use App\Enums\M03\SaleStatus;
use App\Models\M03\Course;
use App\Models\M07\Order;
use App\Models\User;

final class OrderPolicy
{
    public function create(User $user, Course $course): bool
    {
        return $user->hasEffectiveRole(RoleCode::HocVien) && $user->hasVerifiedEmail()
            && $course->current_version_id !== null && $course->sale_status !== SaleStatus::ChuaBan;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasEffectiveRole(RoleCode::HocVien, RoleCode::GiaoVu);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->viewForAcademic($user) || $this->viewForStudent($user, $order);
    }

    public function viewForAcademic(User $user): bool
    {
        return $user->hasEffectiveRole(RoleCode::GiaoVu);
    }

    public function viewForStudent(User $user, Order $order): bool
    {
        return $user->id === $order->user_id && $user->hasEffectiveRole(RoleCode::HocVien);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->viewForStudent($user, $order);
    }

    public function pay(User $user, Order $order): bool
    {
        return $this->viewForStudent($user, $order) && $user->hasVerifiedEmail();
    }

    public function viewAudit(User $user, Order $order): bool
    {
        return $this->viewForAcademic($user);
    }
}
