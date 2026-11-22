<?php

namespace App\Http\Controllers\PublicSite;

use App\Actions\M25\AiAssistant;
use App\Enums\M25\AiKind;
use App\Http\Controllers\Controller;
use App\Models\M11\Setting;
use App\Models\M25\AiConversation;
use App\Models\M25\AiMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** MH-KH tư vấn khóa học (QT-27c): Khách tính lượt theo IP, lịch sử theo phiên trình duyệt (A5); người đăng nhập theo tài khoản. */
class AdvisorController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('public/advisor', self::payload($request));
    }

    /** P10: trạng thái cho hộp chat nổi (JSON). */
    public function state(Request $request): JsonResponse
    {
        return response()->json(self::payload($request));
    }

    /** @return array{enabled: bool, hotline: string, messages: mixed} */
    private static function payload(Request $request): array
    {
        /** @var ?User $user */
        $user = $request->user();
        $query = AiConversation::query()->where('kind', AiKind::TuVan->value);
        $conversation = ($user === null
            ? $query->whereNull('user_id')->where('guest_key', self::guestKey($request))
            : $query->where('user_id', $user->id))->first();

        // CR-257: tư vấn không giới hạn lượt theo ngày, nên không trả số lượt còn lại.
        return [
            'enabled' => Setting::valueFor('ai.enabled') === true,
            'hotline' => (string) config('services.support.hotline'),
            'messages' => $conversation === null ? [] : $conversation->messages()->reorder('id', 'desc')->limit(60)->get()->reverse()->map(fn (AiMessage $m) => [
                'id' => $m->id, 'role' => $m->role, 'content' => $m->content, 'status' => $m->status,
                'courses' => $m->meta['courses'] ?? [],
            ])->values(),
        ];
    }

    public function ask(Request $request, AiAssistant $ai): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:1000'], 'request_key' => ['required', 'string', 'max:64']]);
        /** @var ?User $user */
        $user = $request->user();
        $ai->advise($user, self::guestKey($request), (string) $request->ip(), $data['question'], $data['request_key']);

        return $request->expectsJson() ? response()->json(self::payload($request)) : back();
    }

    /** Khóa lịch sử của Khách: ngẫu nhiên trong phiên, không suy từ IP (A5). */
    private static function guestKey(Request $request): string
    {
        $key = $request->session()->get('ai.guest_key');
        if (! is_string($key) || strlen($key) !== 64) {
            $key = hash('sha256', Str::random(40));
            $request->session()->put('ai.guest_key', $key);
        }

        return $key;
    }
}
