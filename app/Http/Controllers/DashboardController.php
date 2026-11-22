<?php

namespace App\Http\Controllers;

use App\Enums\M01\RoleCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** QT-01 b10 (BR-HT-05, CR-184): đưa vào trang bắt đầu của vai hiệu lực cao nhất. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $route = match (true) {
            $user->hasEffectiveRole(RoleCode::QuanTri) => 'admin.dashboard',
            $user->hasEffectiveRole(RoleCode::GiaoVu) => 'academic.dashboard',
            $user->hasEffectiveRole(RoleCode::GiaoVien) => 'teacher.dashboard',
            $user->hasEffectiveRole(RoleCode::HocVien) => 'student.dashboard',
            $user->hasEffectiveRole(RoleCode::PhuHuynh) => 'guardian.dashboard',
            default => 'account.profile.edit',
        };

        return redirect()->route($route);
    }
}
