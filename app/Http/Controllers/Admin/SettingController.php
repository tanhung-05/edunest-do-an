<?php

namespace App\Http\Controllers\Admin;

use App\Actions\M11\SetGrantFailureMode;
use App\Actions\M25\AiAssistant;
use App\Http\Controllers\Controller;
use App\Models\M11\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MH-QT-04 — cấu hình (CR-22). P0 chỉ đọc: chưa tham số nào có điểm sử dụng; UpdateAllowedSettings
 * bật từng khóa ở mốc dùng nó (đơn P3, thiết bị P4/P5, chứng nhận P4) và ghi audit_logs (P1).
 */
class SettingController extends Controller
{
    public function edit(): Response
    {
        Gate::authorize('view-settings');

        return Inertia::render('admin/settings', [
            'canToggleGrantFailure' => Gate::allows('toggle-grant-failure-mode'),
            'grantFailureMode' => Setting::grantFailureMode(),
            'aiEnabled' => Setting::valueFor('ai.enabled') === true,
            'settings' => Setting::query()->whereNotIn('key', ['grant.fault_mode', 'ai.enabled'])->orderBy('key')->get()->map(fn (Setting $s) => [
                'key' => $s->key,
                'value' => $s->value,
                'editable' => Setting::isEditable($s->key),
            ]),
        ]);
    }

    public function grantFailureMode(Request $request, SetGrantFailureMode $set): RedirectResponse
    {
        Gate::authorize('toggle-grant-failure-mode');
        $request->validate(['enabled' => ['required', 'boolean']]);
        /** @var User $user */
        $user = $request->user();
        $set->handle($user, $request->boolean('enabled'));

        return to_route('admin.settings.edit');
    }

    /** QT-27 A7: Quản trị bật/tắt trợ lý AI (CR-170: cùng dòng cờ với điểm ghi nhận để gửi). */
    public function ai(Request $request, AiAssistant $ai): RedirectResponse
    {
        Gate::authorize('view-settings');
        $request->validate(['enabled' => ['required', 'boolean']]);
        /** @var User $user */
        $user = $request->user();
        $ai->setEnabled($user, $request->boolean('enabled'));

        return to_route('admin.settings.edit');
    }
}
