<?php

namespace App\Http\Controllers\Account;

use App\Actions\M01\ChangePassword;
use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-HV-17 (đổi mật khẩu bắt buộc, A5) và đổi mật khẩu thường ở MH-HV-18. */
class PasswordController extends Controller
{
    use PasswordValidationRules;

    public function required(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->must_change_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('account/password-required');
    }

    public function updateRequired(Request $request, ChangePassword $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'current_password' => $this->currentPasswordRules(),
            'password' => [...$this->passwordRules(), 'different:current_password'],
        ]);
        $action->handle($user, $data['password']);

        return redirect()->route('dashboard')->with('status', 'Đã đổi mật khẩu.');
    }

    public function update(Request $request, ChangePassword $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ]);
        $action->handle($user, $data['password']);

        return back()->with('status', 'Đã đổi mật khẩu.');
    }
}
