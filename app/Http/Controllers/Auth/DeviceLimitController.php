<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\M01\UserDevice;
use App\Models\User;
use App\Support\M16\DeviceRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * QT-17 A1, E20: đăng nhập vượt giới hạn thiết bị — học viên chọn một thiết bị cũ để đăng xuất rồi mới vào.
 * Lần xác thực mật khẩu đã qua lưu ở phiên tối đa 10 phút.
 */
final class DeviceLimitController extends Controller
{
    public const PENDING = 'device.pending';

    public const ADMITTED = 'device.admitted';

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $this->pending($request);
        if ($user === null) {
            return to_route('login');
        }

        return Inertia::render('auth/device-limit', [
            'devices' => DeviceRegistry::active($user)->map(fn (UserDevice $d) => [
                'id' => $d->id, 'browser' => $d->browser ?? 'Trình duyệt', 'os' => $d->os ?? 'Không rõ',
                'last_active_at' => $d->last_active_at->toIso8601String(),
            ])->values(),
        ]);
    }

    public function choose(Request $request): RedirectResponse
    {
        $user = $this->pending($request);
        if ($user === null) {
            return to_route('login');
        }
        $data = $request->validate(['device' => ['required', 'integer']]);
        $device = DeviceRegistry::admit($request, $user, (int) $data['device']);
        if ($device === null) {
            throw ValidationException::withMessages(['device' => 'Vẫn đủ số thiết bị. Hãy chọn một thiết bị khác.']);
        }
        $remember = (bool) ($request->session()->get(self::PENDING)['remember'] ?? false);
        $request->session()->forget(self::PENDING);
        $request->session()->put(self::ADMITTED, $device->id);
        if ($remember) {
            DeviceRegistry::issueRemember($user, $device);
        }
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function pending(Request $request): ?User
    {
        $pending = $request->session()->get(self::PENDING);
        if (! is_array($pending) || ! isset($pending['user'], $pending['at']) || now()->getTimestamp() - (int) $pending['at'] > 600) {
            return null;
        }
        $user = User::query()->find((int) $pending['user']);

        return $user instanceof User && $user->isActive() ? $user : null;
    }
}
