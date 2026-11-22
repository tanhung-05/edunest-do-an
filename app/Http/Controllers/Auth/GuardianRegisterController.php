<?php

namespace App\Http\Controllers\Auth;

use App\Actions\M23\GuardianLinks;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/** MH-KH-xx: Khách đăng ký Phụ huynh bằng mã mời (QT-25 bước 3a–4, BR-MR-02). */
final class GuardianRegisterController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/register-guardian', ['code' => (string) $request->query('code', '')]);
    }

    public function store(Request $request, GuardianLinks $links): RedirectResponse
    {
        $user = $links->register($request->only(['name', 'email', 'password', 'password_confirmation', 'code']));
        event(new Registered($user));
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('guardian.dashboard');
    }
}
