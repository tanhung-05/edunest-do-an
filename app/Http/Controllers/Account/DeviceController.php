<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\M01\UserDevice;
use App\Models\M11\Setting;
use App\Models\User;
use App\Support\M09\LearningFile;
use App\Support\M16\DeviceRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/** MH-HV-16 (QT-17 b12–13, A9; NH-23). */
final class DeviceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->actor($request);
        $cookie = DeviceRegistry::cookieIfPresent($request);
        $currentHash = $cookie === null ? null : LearningFile::cookieHash($user, $cookie);

        return Inertia::render('account/devices', [
            'limit' => DeviceRegistry::limited($user) ? Setting::number('device.max_active') : null,
            'devices' => UserDevice::query()->where('user_id', $user->id)->whereNull('revoked_at')
                ->orderByDesc('last_active_at')->get()->map(fn (UserDevice $d) => [
                    'id' => $d->id, 'browser' => $d->browser ?? 'Trình duyệt', 'os' => $d->os ?? 'Không rõ',
                    'last_active_at' => $d->last_active_at->toIso8601String(),
                    'active' => $d->last_active_at->gte(now()->subDays(DeviceRegistry::ACTIVE_DAYS)),
                    'current' => $currentHash !== null && hash_equals($d->cookie_hash, $currentHash),
                ])->values(),
        ]);
    }

    public function destroy(Request $request, int $device): RedirectResponse
    {
        $user = $this->actor($request);
        $current = UserDevice::query()->where('user_id', $user->id)->find($device);
        $cookie = DeviceRegistry::cookieIfPresent($request);
        $isCurrent = $current !== null && $cookie !== null && hash_equals($current->cookie_hash, LearningFile::cookieHash($user, $cookie));
        DeviceRegistry::revoke($user, $device);
        if ($isCurrent) {
            // A9: đăng xuất chính thiết bị đang dùng.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login');
        }

        return back()->with('status', 'Đã đăng xuất thiết bị.');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
