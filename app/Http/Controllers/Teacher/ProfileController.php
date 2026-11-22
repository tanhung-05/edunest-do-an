<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\M02\UpdateTeacherProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** MH-GV-02 — hồ sơ giáo viên của tôi (QT-02 b6–7, NH-54 update). */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $p = $user->teacherProfile;

        return Inertia::render('teacher/profile', [
            'profile' => $p ? [
                'bio' => $p->bio,
                'achievements' => $p->achievements,
                'photo' => $p->photo_path ? Storage::disk('public')->url($p->photo_path) : null,
            ] : null,
            'publicUrl' => route('public.teachers.show', $user->id),
        ]);
    }

    public function update(Request $request, UpdateTeacherProfile $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        // AC-02-23: Giáo viên chỉ sửa hồ sơ của mình.
        abort_if($request->filled('user_id') && (int) $request->input('user_id') !== $user->id, 403, 'Bạn chỉ sửa được hồ sơ của mình.');
        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:5000'],
            'achievements' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);
        $action->handle($user, ['bio' => $data['bio'] ?? null, 'achievements' => $data['achievements'] ?? null], $request->file('photo'));

        return back()->with('status', 'Đã lưu hồ sơ giáo viên.');
    }
}
