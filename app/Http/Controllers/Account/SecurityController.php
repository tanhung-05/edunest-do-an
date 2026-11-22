<?php

namespace App\Http\Controllers\Account;

use App\Enums\M01\RoleCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-HV-18 — mật khẩu và bảo mật; đích của staff.2fa. 2FA chỉ cho vai Giáo vụ/Quản trị (CR-190). */
class SecurityController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('account/security', [
            'canSetupTwoFactor' => $user->hasRole(RoleCode::GiaoVu) || $user->hasRole(RoleCode::QuanTri),
            'twoFactorEnabled' => $user->hasTwoFactorConfirmed(),
            'twoFactorPending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
            'mustSetupTwoFactor' => $user->hasPendingStaffRole(),
            'status' => $request->session()->get('status'),
        ]);
    }
}
