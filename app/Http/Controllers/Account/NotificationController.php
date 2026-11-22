<?php

namespace App\Http\Controllers\Account;

use App\Enums\M15\NotificationKind;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\EduNotification;
use App\Support\M15\Notifier;
use App\Support\M15\WebPushConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/** MH-HV-14 (M15, BR-HT-46). */
final class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->actor($request);
        $page = $user->notifications()->latest()->paginate(20);

        return Inertia::render('account/notifications', [
            'notifications' => $page->through(fn (DatabaseNotification $n) => [
                'id' => $n->id, 'kind' => $n->type, 'data' => $n->data,
                'read' => $n->read_at !== null, 'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'kinds' => array_map(fn (NotificationKind $k) => [
                'code' => $k->value, 'label' => $k->label(), 'mutable' => $k->mutable(),
                'muted' => in_array($k->value, Notifier::mutes($user), true),
            ], NotificationKind::cases()),
            // CR-262: khóa công khai VAPID để trình duyệt đăng ký nhận thông báo đẩy; null thì ẩn nút bật.
            'push_public_key' => WebPushConfig::enabled() ? WebPushConfig::publicKey() : null,
        ]);
    }

    /** Dự phòng khi chưa chạy Reverb: giao diện thăm dò mỗi 30 giây. */
    public function unread(Request $request): JsonResponse
    {
        $user = $this->actor($request);
        $latest = $user->unreadNotifications()->latest()->first();

        return response()->json([
            'count' => $user->unreadNotifications()->count(),
            'latest' => $latest === null ? null : ['id' => $latest->id, 'data' => $latest->data],
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $user = $this->actor($request);
        $row = $user->notifications()->whereKey($notification)->firstOrFail();
        $row->markAsRead();
        $url = $row->data['url'] ?? null;
        // Thông báo cũ lưu đường dẫn tuyệt đối theo APP_URL: đổi về đường dẫn trong ứng dụng.
        $url = is_string($url) ? EduNotification::relative($url) : null;

        // P9.2: chỉ chuyển tới đường dẫn trong ứng dụng, phòng dữ liệu thông báo chứa link ngoài.
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
        $internal = is_string($url) && $url !== '' && (str_starts_with($url, '/') && ! str_starts_with($url, '//')
            || ($host !== null && $host === $request->getHost()));

        return $internal ? redirect()->to($url) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->actor($request)->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    public function preferences(Request $request): RedirectResponse
    {
        $user = $this->actor($request);
        $data = $request->validate(['muted' => ['array'], 'muted.*' => ['string']]);
        $allowed = array_map(fn (NotificationKind $k) => $k->value, array_filter(NotificationKind::cases(), fn ($k) => $k->mutable()));
        $user->forceFill(['notification_mutes' => array_values(array_intersect($data['muted'] ?? [], $allowed))])->save();

        return back()->with('status', 'Đã lưu tùy chọn thông báo.');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
