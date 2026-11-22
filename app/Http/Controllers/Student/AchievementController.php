<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\M21\Achievements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-HV-18: XP, cấp, chuỗi ngày, huy hiệu, sổ XP, bảng xếp hạng (QT-23 bước 6–7). */
final class AchievementController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $period = $request->query('period') === 'month' ? 'month' : 'week';

        return Inertia::render('student/achievements', [
            'summary' => Achievements::summary($user),
            'ledger' => Achievements::ledger($user),
            'leaderboard' => Achievements::leaderboard($user, $period),
            'profile' => ['nickname' => $user->nickname, 'show_name' => $user->show_name_on_leaderboard, 'name' => $user->name,
                'public_name' => Achievements::publicName($user)],
        ]);
    }

    /** CR-157/229: biệt danh và đồng ý công khai tên trên bảng XP. */
    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate(['nickname' => ['nullable', 'string', 'max:30'], 'show_name' => ['boolean']]);
        $user = $this->user($request);
        $nickname = trim((string) ($data['nickname'] ?? ''));
        $user->forceFill(['nickname' => $nickname === '' ? null : $nickname, 'show_name_on_leaderboard' => (bool) ($data['show_name'] ?? false)])->save();

        return back()->with('status', 'Đã lưu tên hiển thị trên bảng xếp hạng.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
