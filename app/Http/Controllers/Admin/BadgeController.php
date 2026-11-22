<?php

namespace App\Http\Controllers\Admin;

use App\Actions\M21\BadgeAdmin;
use App\Enums\M21\BadgeRule;
use App\Http\Controllers\Controller;
use App\Models\M21\Badge;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** QT-23 A1a/A1b: Quản trị định nghĩa huy hiệu (BR-XP-71). */
final class BadgeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/badges/index', [
            'badges' => Badge::query()->orderBy('id')->get()->map(fn (Badge $b) => [
                'id' => $b->id, 'code' => $b->code, 'name' => $b->name, 'description' => $b->description, 'rule' => $b->rule->value,
                'threshold' => $b->threshold, 'is_active' => $b->is_active, 'awarded' => DB::table('badge_user')->where('badge_id', $b->id)->count(),
                'update_url' => route('admin.badges.update', $b),
            ])->values(),
            'rules' => collect(BadgeRule::cases())->map(fn (BadgeRule $r) => ['value' => $r->value, 'label' => $r->label()])->values(),
            'store_url' => route('admin.badges.store'),
        ]);
    }

    public function store(Request $request, BadgeAdmin $admin): RedirectResponse
    {
        $admin->save($this->actor($request), $this->data($request));

        return back()->with('status', 'Đã tạo huy hiệu.');
    }

    public function update(Request $request, Badge $badge, BadgeAdmin $admin): RedirectResponse
    {
        $admin->save($this->actor($request), $this->data($request), $badge);

        return back()->with('status', 'Đã lưu huy hiệu.');
    }

    /** @return array{name: string, description: ?string, rule: string, threshold: int, is_active: bool} */
    private function data(Request $request): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500'],
            'rule' => ['required', Rule::enum(BadgeRule::class)], 'threshold' => ['required', 'integer', 'min:1', 'max:100000'], 'is_active' => ['boolean']]);

        return ['name' => $data['name'], 'description' => $data['description'] ?? null, 'rule' => $data['rule'], 'threshold' => (int) $data['threshold'],
            'is_active' => (bool) ($data['is_active'] ?? true)];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
