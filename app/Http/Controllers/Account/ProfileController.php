<?php

namespace App\Http\Controllers\Account;

use App\Actions\M01\UpdateOwnProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-HV-15 — hồ sơ cá nhân (QT-01 A15, BR-HT-18). Email không sửa được (BR-HT-55). */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('account/profile', ['status' => $request->session()->get('status')]);
    }

    public function update(Request $request, UpdateOwnProfile $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        // AC-01-33: chỉ sửa hồ sơ của chính mình; yêu cầu nhắm tài khoản khác bị từ chối.
        abort_if($request->filled('user_id') && (int) $request->input('user_id') !== $user->id, 403, 'Bạn chỉ sửa được hồ sơ của mình.');
        // AC-01-45: không đổi email đăng nhập.
        if ($request->filled('email') && User::normalizeEmail((string) $request->input('email')) !== $user->email) {
            return back()->withErrors(['email' => 'Không thể đổi email đăng nhập.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);
        $action->handle($user, $data['name'], $request->file('avatar'));

        return back()->with('status', 'Đã lưu hồ sơ.');
    }
}
