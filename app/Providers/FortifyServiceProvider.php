<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\M01\ConfirmTwoFactorSetup;
use App\Actions\M01\CreateAuthenticatedSession;
use App\Http\Controllers\Auth\DeviceLimitController;
use App\Http\Responses\SamePasswordResetLinkResponse;
use App\Models\User;
use App\Support\M16\DeviceRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Tuyến Fortify khai báo lại ở routes/auth.php: bỏ passkey, tắt 2FA, gắn middleware 04 §2.
        Fortify::ignoreRoutes();

        $this->app->bind(ConfirmTwoFactorAuthentication::class, ConfirmTwoFactorSetup::class);
        // AC-01-40 (BR-HT-58): phản hồi quên mật khẩu giống nhau dù email có tồn tại hay không.
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, SamePasswordResetLinkResponse::class);
        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponse::class, SamePasswordResetLinkResponse::class);
    }

    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * QT-01 b8, A3, A4: sai email/mật khẩu → một thông báo chung; tài khoản BI_KHOA chỉ báo khóa khi
     * mật khẩu đúng. Ghi thế hệ xác thực cho bước đăng nhập dở (DT-26).
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::query()->where('email', User::normalizeEmail((string) $request->input('email')))->first();
            if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }
            if (! $user->isActive()) {
                throw ValidationException::withMessages(['email' => 'Tài khoản của bạn đã bị khóa.']);
            }
            $request->session()->put(CreateAuthenticatedSession::PENDING_KEY, $user->auth_generation);

            // QT-17 b3–5, E20: học viên vượt giới hạn thiết bị phải chọn đăng xuất một thiết bị cũ.
            if (DeviceRegistry::limited($user)) {
                $device = DeviceRegistry::admit($request, $user);
                if ($device === null) {
                    $request->session()->put(DeviceLimitController::PENDING, [
                        'user' => $user->id, 'remember' => $request->boolean('remember'), 'at' => now()->getTimestamp(),
                    ]);
                    throw new HttpResponseException(redirect()->route('login.devices'));
                }
                $request->session()->put(DeviceLimitController::ADMITTED, $device->id);
                if ($request->boolean('remember')) {
                    DeviceRegistry::issueRemember($user, $device);
                }
            }

            return $user;
        });
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
