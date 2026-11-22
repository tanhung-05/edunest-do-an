<?php

namespace App\Http\Controllers\Academic;

use App\Actions\M29\SupportChat;
use App\Http\Controllers\Controller;
use App\Models\M29\SupportMessage;
use App\Models\M29\SupportThread;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

/** P10 M29: hộp thư chat hỗ trợ của Giáo vụ. Danh sách theo trạng thái, khung trò chuyện bên phải. */
class SupportInboxController extends Controller
{
    private const FILTERS = ['mo', 'cho', 'dong', 'tat-ca'];

    public function index(Request $request, SupportChat $chat): Response
    {
        $filter = in_array($request->query('loc'), self::FILTERS, true) ? (string) $request->query('loc') : 'mo';
        $threads = SupportThread::query()->with(['user:id,name,email', 'assignedTo:id,name'])
            ->when($filter === 'mo', fn ($q) => $q->where('status', '!=', SupportThread::DONG))
            ->when($filter === 'cho', fn ($q) => $q->where('status', SupportThread::CHO))
            ->when($filter === 'dong', fn ($q) => $q->where('status', SupportThread::DONG))
            ->orderByRaw("status = 'CHO' desc")->orderByDesc('last_message_at')->limit(100)->get();

        $activeId = $request->integer('thread');
        $active = $activeId > 0 ? SupportThread::query()->with('user:id,name,email')->find($activeId) : null;
        $messages = [];
        if ($active !== null) {
            /** @var User $agent */
            $agent = $request->user();
            $chat->markRead($agent, $active);
            $messages = SupportMessage::query()->with('sender:id,name')->where('support_thread_id', $active->id)
                ->orderByDesc('id')->limit(200)->get()->reverse()->map(fn (SupportMessage $m) => $m->toChat($active->guest_name))->values()->all();
        }

        return Inertia::render('academic/support/index', [
            'filter' => $filter,
            'threads' => $threads->map(fn (SupportThread $t) => [
                'id' => $t->id, 'status' => $t->status, 'user' => $t->displayName(), 'email' => $t->user?->email,
                'phone' => $t->guest_phone, 'is_guest' => $t->user_id === null,
                'staff_unread' => $active?->id === $t->id ? 0 : $t->staff_unread,
                'assigned_to' => $t->assignedTo?->name,
                'last_message_at' => $t->last_message_at?->toIso8601String(),
            ])->values(),
            'active' => $active === null ? null : [
                'id' => $active->id, 'status' => $active->status, 'user' => $active->displayName(), 'email' => $active->user?->email,
                'phone' => $active->guest_phone, 'is_guest' => $active->user_id === null,
            ],
            'messages' => $messages,
        ]);
    }

    public function reply(Request $request, SupportThread $thread, SupportChat $chat): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'body' => ['required', 'string', 'max:'.SupportChat::MAX_BODY],
            'request_key' => ['required', 'string', 'max:64'],
        ])->validate();
        /** @var User $agent */
        $agent = $request->user();
        $chat->reply($agent, $thread, (string) $data['body'], (string) $data['request_key']);

        return back();
    }

    public function close(Request $request, SupportThread $thread, SupportChat $chat): RedirectResponse
    {
        /** @var User $agent */
        $agent = $request->user();
        $chat->close($agent, $thread);

        return back()->with('success', 'Đã đóng cuộc trò chuyện. Học viên nhắn lại thì cuộc trò chuyện tự mở lại.');
    }
}
