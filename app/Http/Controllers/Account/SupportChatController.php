<?php

namespace App\Http\Controllers\Account;

use App\Actions\M29\SupportChat;
use App\Http\Controllers\Controller;
use App\Models\M29\SupportMessage;
use App\Models\M29\SupportThread;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * P10 M29: phía người hỏi của chat với nhân viên (hộp chat nổi, JSON). Người đăng nhập theo tài khoản; Khách theo khóa
 * ngẫu nhiên trong phiên trình duyệt (CR-258), không suy từ IP.
 */
class SupportChatController extends Controller
{
    public function state(Request $request): JsonResponse
    {
        return response()->json(self::payload($request));
    }

    public function send(Request $request, SupportChat $chat): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.SupportChat::MAX_BODY],
            'request_key' => ['required', 'string', 'max:64'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
        ]);
        /** @var ?User $user */
        $user = $request->user();
        $chat->send($user, $user === null ? self::guestKey($request) : null, $data['body'], $data['request_key'],
            $data['guest_name'] ?? null, $data['guest_phone'] ?? null);

        return response()->json(self::payload($request));
    }

    public function read(Request $request, SupportChat $chat): JsonResponse
    {
        $thread = self::thread($request);
        if ($thread !== null) {
            /** @var ?User $user */
            $user = $request->user();
            $chat->markRead($user, $thread, $user === null ? self::guestKey($request) : null);
        }

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private static function payload(Request $request): array
    {
        /** @var ?User $user */
        $user = $request->user();
        $thread = self::thread($request);
        $messages = $thread === null ? [] : SupportMessage::query()->with('sender:id,name')
            ->where('support_thread_id', $thread->id)->orderByDesc('id')->limit(100)->get()->reverse()
            ->map(fn (SupportMessage $m) => $m->toChat($thread->guest_name))->values()->all();

        return [
            'thread_id' => $thread?->id,
            'status' => $thread->status ?? null,
            'unread' => $thread->user_unread ?? 0,
            'is_agent' => $user !== null && SupportChat::isAgent($user),
            'is_guest' => $user === null,
            // Khách đã nhắn thì điền sẵn tên, số điện thoại lần trước (chỉ trả cho đúng phiên đó).
            'guest' => $user === null && $thread !== null ? ['name' => $thread->guest_name, 'phone' => $thread->guest_phone] : null,
            'hotline' => (string) config('services.support.hotline'),
            'messages' => $messages,
        ];
    }

    private static function thread(Request $request): ?SupportThread
    {
        /** @var ?User $user */
        $user = $request->user();
        if ($user !== null) {
            return SupportThread::query()->where('user_id', $user->id)->first();
        }
        $key = $request->session()->get('support.guest_key');

        return is_string($key) ? SupportThread::query()->where('guest_key', $key)->first() : null;
    }

    private static function guestKey(Request $request): string
    {
        $key = $request->session()->get('support.guest_key');
        if (! is_string($key) || preg_match('/^[a-f0-9]{64}$/', $key) !== 1) {
            $key = hash('sha256', Str::random(40));
            $request->session()->put('support.guest_key', $key);
        }

        return $key;
    }
}
