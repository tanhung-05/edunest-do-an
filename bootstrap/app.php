<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureApiDevice;
use App\Http\Middleware\EnsureBoundDevice;
use App\Http\Middleware\EnsureEffectiveRole;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureStaffTwoFactor;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackRequestIp;
use App\Support\DomainRejection;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [__DIR__.'/../routes/auth.php', __DIR__.'/../routes/web.php'],
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['webhooks/payment-sim', 'webhooks/bank-sim/*']);
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            TrackRequestIp::class,
        ]);

        // 04 §2
        $middleware->alias([
            'account.active' => EnsureAccountActive::class,
            'device.bound' => EnsureBoundDevice::class,
            'password.changed' => EnsurePasswordChanged::class,
            'staff.2fa' => EnsureStaffTwoFactor::class,
            'role' => EnsureEffectiveRole::class,
            'api.device' => EnsureApiDevice::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Từ chối nghiệp vụ dưới khóa: 422 gắn trường hoặc 403/404.
        $exceptions->map(DomainRejection::class, fn (DomainRejection $e) => $e->status === 422
            ? $e->toValidation()
            : new HttpException($e->status, $e->getMessage()));

        // MH-KH-16: trang lỗi Inertia cho 403, 404, 419, 429, 503.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            if (! in_array($status, [403, 404, 419, 429, 503], true) || $request->expectsJson()) {
                return $response;
            }
            if ($status === 419) {
                return back()->with('status', 'Trang đã hết hạn, hãy thử lại.');
            }
            $message = $e instanceof HttpException ? $e->getMessage() : '';
            $retryAfter = $response->headers->get('Retry-After');

            return Inertia::render('error', [
                'status' => $status,
                'message' => $message,
                'retryAfter' => $retryAfter !== null ? (int) $retryAfter : null,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
