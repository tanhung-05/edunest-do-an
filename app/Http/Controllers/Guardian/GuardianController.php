<?php

namespace App\Http\Controllers\Guardian;

use App\Actions\M23\GuardianLinks;
use App\Http\Controllers\Controller;
use App\Models\M23\GuardianLink;
use App\Models\User;
use App\Queries\M23\ChildReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-PH-01/02: Phụ huynh nhập mã, xem danh sách con, xem năm nhóm dữ liệu của một con, gỡ liên kết (QT-25). */
final class GuardianController extends Controller
{
    public function index(Request $request): Response
    {
        $guardian = $this->user($request);
        $links = GuardianLink::query()->where('guardian_id', $guardian->id)->whereNull('removed_at')->with('student:id,name')->orderBy('linked_at')->get();

        return Inertia::render('guardian/index', [
            'children' => $links->map(fn (GuardianLink $l) => ['link_id' => $l->id, 'id' => $l->student_id, 'name' => $l->student?->name,
                'linked_at' => $l->linked_at->toISOString()])->values(),
        ]);
    }

    public function link(Request $request, GuardianLinks $links): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $links->link($this->user($request), $data['code']);

        return back()->with('status', 'Đã liên kết với con.');
    }

    /** Bước 5–7: mỗi lần đọc kiểm lại vai và liên kết hiện tại (BR-MR-05, X2). */
    public function show(Request $request, int $student): Response
    {
        $guardian = $this->user($request);
        $link = GuardianLinks::active($guardian, $student);
        abort_if($link === null, 403, 'Bạn không có liên kết với học viên này.');
        $child = User::query()->findOrFail($student);

        return Inertia::render('guardian/child', ['report' => ChildReport::for($child), 'link_id' => $link->id]);
    }

    public function unlink(Request $request, GuardianLink $link, GuardianLinks $links): RedirectResponse
    {
        $links->unlink($this->user($request), $link->id);

        return redirect()->route('guardian.dashboard')->with('status', 'Đã gỡ liên kết.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
