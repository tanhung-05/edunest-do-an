<?php

namespace App\Http\Middleware;

use App\Enums\M01\RoleCode;
use App\Models\M12\CartItem;
use App\Models\M24\Affiliate;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => fn () => ($u = $request->user()) instanceof User ? [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'avatar' => $u->avatarUrl(),
                    'email_verified_at' => $u->email_verified_at?->toIso8601String(),
                    'two_factor_enabled' => $u->hasTwoFactorConfirmed(),
                    'roles' => array_map(fn (RoleCode $c) => $c->value, $u->roleCodes()),
                    'effective_roles' => array_map(fn (RoleCode $c) => $c->value, $u->effectiveRoles()),
                    // P8 M24: hiện mục "Tiếp thị liên kết" khi học viên có hồ sơ cộng tác viên (kể cả đã tắt, để xem hoa hồng cũ).
                    'is_affiliate' => Affiliate::query()->where('user_id', $u->id)->exists(),
                ] : null,
            ],
            // P5 — chuông (MH-HV-14) và số khóa trong giỏ (MH-HV-11).
            'notifications' => fn () => ($u = $request->user()) instanceof User ? [
                'unread' => $u->unreadNotifications()->count(),
                'reverb' => config('broadcasting.default') === 'reverb',
            ] : null,
            'cart_count' => fn () => ($u = $request->user()) instanceof User
                ? CartItem::query()->whereHas('cart', fn ($q) => $q->where('user_id', $u->id))->count() : 0,
            'flash' => [
                'status' => fn () => $request->hasSession() ? $request->session()->get('status') : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
