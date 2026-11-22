<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\M15\PushSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CR-262: lưu hoặc gỡ đăng ký thông báo đẩy của trình duyệt hiện tại. */
final class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $endpoint = (string) $data['endpoint'];
        PushSubscription::query()->updateOrCreate(['endpoint_hash' => hash('sha256', $endpoint)], [
            'user_id' => $user->id, 'endpoint' => $endpoint,
            'p256dh' => (string) $data['keys']['p256dh'], 'auth' => (string) $data['keys']['auth'],
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json(['status' => 'Đã bật thông báo đẩy trên trình duyệt này.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        /** @var User $user */
        $user = $request->user();
        PushSubscription::query()->where('user_id', $user->id)->where('endpoint_hash', hash('sha256', (string) $data['endpoint']))->delete();

        return response()->json(['status' => 'Đã tắt thông báo đẩy trên trình duyệt này.']);
    }
}
