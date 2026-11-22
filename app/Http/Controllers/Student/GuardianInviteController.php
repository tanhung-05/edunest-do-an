<?php

namespace App\Http\Controllers\Student;

use App\Actions\M23\GuardianLinks;
use App\Http\Controllers\Controller;
use App\Models\M23\GuardianInvite;
use App\Models\M23\GuardianLink;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-HV-21: học viên tạo mã mời phụ huynh, xem và gỡ liên kết (QT-25 bước 1–2, A1). */
final class GuardianInviteController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $this->user($request);
        $now = CarbonImmutable::now();

        return Inertia::render('student/guardians', [
            'invites' => GuardianInvite::query()->where('student_id', $student->id)->latest('issued_at')->limit(10)->get()
                ->map(fn (GuardianInvite $i) => ['id' => $i->id, 'code' => $i->code, 'issued_at' => $i->issued_at->toISOString(),
                    'expires_at' => $i->expires_at->toISOString(),
                    'state' => $i->used_at !== null ? 'DA_DUNG' : ($now->greaterThan($i->expires_at) ? 'HET_HAN' : 'CON_HAN')])->values(),
            'guardians' => GuardianLink::query()->where('student_id', $student->id)->whereNull('removed_at')->with('guardian:id,name,email')
                ->orderBy('linked_at')->get()
                ->map(fn (GuardianLink $l) => ['link_id' => $l->id, 'name' => $l->guardian?->name, 'email' => $l->guardian?->email,
                    'linked_at' => $l->linked_at->toISOString()])->values(),
        ]);
    }

    public function store(Request $request, GuardianLinks $links): RedirectResponse
    {
        $invite = $links->issue($this->user($request));

        return back()->with('status', 'Mã mời mới: '.$invite->code.' (dùng một lần, hạn 7 ngày).');
    }

    public function unlink(Request $request, GuardianLink $link, GuardianLinks $links): RedirectResponse
    {
        $links->unlink($this->user($request), $link->id);

        return back()->with('status', 'Đã gỡ liên kết phụ huynh.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
