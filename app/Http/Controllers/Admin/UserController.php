<?php

namespace App\Http\Controllers\Admin;

use App\Actions\M01\ChangeAccountRoles;
use App\Actions\M01\LockAccount;
use App\Actions\M01\ProvisionAccount;
use App\Actions\M01\ResetStaffTwoFactor;
use App\Actions\M01\UnlockAccount;
use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** MH-QT-02 — tài khoản và vai trò (QT-01 A10–A14, A16; NH-20, NH-21, NH-22, NH-53). */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);
        $search = (string) $request->query('q', '');
        $role = (string) $request->query('role', '');
        $status = (string) $request->query('status', '');

        $users = User::query()->with('roles')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when(RoleCode::tryFrom($role), fn ($q, RoleCode $r) => $q->whereHas('roles', fn ($w) => $w->where('code', $r->value)))
            ->when(AccountStatus::tryFrom($status), fn ($q, AccountStatus $s) => $q->where('status', $s->value))
            ->orderBy('id')
            ->paginate(20)->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'status' => $u->status->value,
                'status_label' => $u->status->label(),
                'roles' => $u->roles->map(fn ($r) => $r->code->label())->values(),
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => ['q' => $search, 'role' => $role, 'status' => $status],
            'roleOptions' => self::roleOptions(),
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        Gate::authorize('view', $user);
        /** @var User $actor */
        $actor = $request->user();
        $user->load('roles');

        return Inertia::render('admin/users/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status->value,
                'status_label' => $user->status->label(),
                'locked_at' => $user->locked_at?->toIso8601String(),
                'locked_reason' => $user->locked_reason,
                'two_factor_enabled' => $user->hasTwoFactorConfirmed(),
                'must_change_password' => $user->must_change_password,
                'roles' => $user->roles->map(fn ($r) => [
                    'code' => $r->code->value,
                    'label' => $r->code->label(),
                    'pending_two_factor' => $r->code->requiresTwoFactor() && ! $user->hasTwoFactorConfirmed(),
                ])->values(),
            ],
            'can' => [
                'lock' => $user->isActive() && $actor->can('lock', $user),
                'unlock' => ! $user->isActive() && $actor->can('unlock', $user),
                'resetTwoFactor' => $user->hasTwoFactorConfirmed() && $actor->can('resetTwoFactor', $user),
            ],
            'roleOptions' => self::roleOptions(),
        ]);
    }

    public function store(Request $request, ProvisionAccount $action): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(RoleCode::class)->only(RoleCode::assignableInP0())],
        ]);
        $role = RoleCode::from($data['role']);
        Gate::authorize('create', [User::class, $role]);
        /** @var User $actor */
        $actor = $request->user();
        $user = $action->handle($actor, $data['name'], $data['email'], $data['password'], $role);

        return redirect()->route('admin.users.show', $user)->with('status', 'Đã cấp tài khoản.');
    }

    public function assignRole(Request $request, User $user, ChangeAccountRoles $action): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::enum(RoleCode::class)->only(RoleCode::assignableInP0())]]);
        $role = RoleCode::from($data['role']);
        Gate::authorize('assignRole', [$user, $role]);
        /** @var User $actor */
        $actor = $request->user();
        $action->assign($actor, $user, $role);

        return back()->with('status', 'Đã gán vai '.$role->label().'.');
    }

    public function removeRole(Request $request, User $user, string $role, ChangeAccountRoles $action): RedirectResponse
    {
        $code = RoleCode::tryFrom($role) ?? abort(404);
        Gate::authorize('removeRole', [$user, $code]);
        /** @var User $actor */
        $actor = $request->user();
        $action->remove($actor, $user, $code);

        return back()->with('status', 'Đã gỡ vai '.$code->label().'.');
    }

    public function lock(Request $request, User $user, LockAccount $action): RedirectResponse
    {
        Gate::authorize('lock', $user);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($actor, $user, $data['reason'] ?? null);

        return back()->with('status', 'Đã khóa tài khoản và thu hồi mọi phiên.');
    }

    public function unlock(Request $request, User $user, UnlockAccount $action): RedirectResponse
    {
        Gate::authorize('unlock', $user);
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($actor, $user);

        return back()->with('status', 'Đã mở khóa tài khoản.');
    }

    public function resetTwoFactor(Request $request, User $user, ResetStaffTwoFactor $action): RedirectResponse
    {
        Gate::authorize('resetTwoFactor', $user);
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($actor, $user);

        return back()->with('status', 'Đã đặt lại 2FA. Người dùng phải thiết lập lại ở lần đăng nhập kế tiếp.');
    }

    /** @return list<array{value: string, label: string}> */
    private static function roleOptions(): array
    {
        return array_map(fn (RoleCode $r) => ['value' => $r->value, 'label' => $r->label()], RoleCode::assignableInP0());
    }
}
