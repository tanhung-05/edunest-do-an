<?php

use App\Http\Controllers\Auth\DeviceLimitController;
use App\Http\Controllers\Auth\GuardianRegisterController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;
use Laravel\Fortify\Http\Controllers\ConfirmedTwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationPromptController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorSecretKeyController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;

/*
| Tuyến Fortify khai báo lại (MH-KH-07..11, MH-HV-18). Bỏ passkey (01 Q-01), không có tuyến tắt 2FA
| (BR-HT-14, MH-HV-18) hay xóa tài khoản (CR-12). Thiết lập 2FA chỉ cho tài khoản có vai nhân sự (CR-190).
*/

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    // QT-17 A1/E20: chọn thiết bị cũ để đăng xuất khi vượt giới hạn.
    Route::get('/login/devices', [DeviceLimitController::class, 'show'])->name('login.devices');
    Route::post('/login/devices', [DeviceLimitController::class, 'choose'])->middleware('throttle:10,1')->name('login.devices.choose');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    // P8 — QT-25 bước 3a: đăng ký Phụ huynh bằng mã mời.
    Route::get('/register/guardian', [GuardianRegisterController::class, 'create'])->name('register.guardian.create');
    Route::post('/register/guardian', [GuardianRegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.guardian.store');

    Route::get('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'create'])->name('two-factor.login');
    Route::post('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:two-factor')->name('two-factor.login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:web')->name('logout');

Route::middleware(['auth:web', 'account.active'])->group(function () {
    Route::get('/email/verify', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')->name('verification.send');

    Route::get('/user/confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('/user/confirm-password', [ConfirmablePasswordController::class, 'store'])->name('password.confirm.store');
    Route::get('/user/confirmed-password-status', [ConfirmedPasswordStatusController::class, 'show'])->name('password.confirmation');

    // Thiết lập 2FA: mở cả khi bị buộc thiết lập (không qua staff.2fa), sau bước đổi mật khẩu bắt buộc.
    Route::middleware(['password.changed', 'can:setup-two-factor'])->group(function () {
        Route::post('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])->name('two-factor.enable');
        Route::post('/user/confirmed-two-factor-authentication', [ConfirmedTwoFactorAuthenticationController::class, 'store'])->name('two-factor.confirm');
        Route::get('/user/two-factor-qr-code', [TwoFactorQrCodeController::class, 'show'])->name('two-factor.qr-code');
        Route::get('/user/two-factor-secret-key', [TwoFactorSecretKeyController::class, 'show'])->name('two-factor.secret-key');
        Route::get('/user/two-factor-recovery-codes', [RecoveryCodeController::class, 'index'])->name('two-factor.recovery-codes');
        Route::post('/user/two-factor-recovery-codes', [RecoveryCodeController::class, 'store'])
            ->middleware('password.confirm')->name('two-factor.regenerate-recovery-codes');
    });
});
