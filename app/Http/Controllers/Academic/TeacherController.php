<?php

namespace App\Http\Controllers\Academic;

use App\Actions\M01\ProvisionAccount;
use App\Actions\M02\CreateTeacherProfile;
use App\Actions\M02\SetTeacherSubjects;
use App\Concerns\PasswordValidationRules;
use App\Enums\M01\RoleCode;
use App\Http\Controllers\Controller;
use App\Models\M02\Category;
use App\Models\M02\TeacherProfile;
use App\Models\User;
use App\Queries\M01\TeacherList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** MH-GVU-02 — tài khoản giáo viên (QT-01 A9, QT-02 b4–5, NH-22, NH-54, NH-87). */
class TeacherController extends Controller
{
    use PasswordValidationRules;

    public function index(Request $request, TeacherList $query): Response
    {
        Gate::authorize('viewTeachers', User::class);

        return Inertia::render('academic/teachers', [
            'teachers' => $query->get((string) $request->query('q', '')),
            'filters' => ['q' => (string) $request->query('q', '')],
            // CR-259: danh mục môn để chọn chuyên môn.
            'subjects' => self::subjectOptions(),
        ]);
    }

    public function store(Request $request, ProvisionAccount $action): RedirectResponse
    {
        Gate::authorize('create', [User::class, RoleCode::GiaoVien]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'achievements' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $photo = $request->file('photo')?->store('teacher-photos', 'public');
        $action->handle($actor, $data['name'], $data['email'], $data['password'], RoleCode::GiaoVien, [
            'bio' => $data['bio'] ?? null,
            'achievements' => $data['achievements'] ?? null,
            'photo_path' => is_string($photo) ? $photo : null,
        ]);

        return back()->with('status', 'Đã cấp tài khoản Giáo viên. Người dùng phải đổi mật khẩu ở lần đăng nhập đầu.');
    }

    /** Lập hồ sơ cho tài khoản Giáo viên chưa có hồ sơ (NH-54 create, chỉ Giáo vụ). */
    public function storeProfile(Request $request, User $user, CreateTeacherProfile $action): RedirectResponse
    {
        Gate::authorize('create', TeacherProfile::class);
        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:5000'],
            'achievements' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($actor, $user, $data);

        return back()->with('status', 'Đã lập hồ sơ giáo viên.');
    }

    /** CR-259 (UAT 22/11): Giáo vụ ghi chuyên môn giáo viên để gợi ý khi phân công soạn khóa. */
    public function subjects(Request $request, User $user, SetTeacherSubjects $action): RedirectResponse
    {
        Gate::authorize('viewTeachers', User::class);
        $data = $request->validate([
            'subject_category_ids' => ['present', 'array'],
            'subject_category_ids.*' => ['integer'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        /** @var array<int, mixed> $ids */
        $ids = $data['subject_category_ids'];
        $action->handle($actor, $user, array_values(array_map('intval', $ids)));

        return back()->with('status', 'Đã lưu chuyên môn của '.$user->name.'.');
    }

    /** @return list<array{id: int, name: string}> */
    public static function subjectOptions(): array
    {
        $cats = Category::query()->orderBy('position')->orderBy('name')->get()->toBase();

        return array_values($cats->filter(fn (Category $c) => $c->parent_id !== null || $cats->where('parent_id', $c->id)->isEmpty())
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->parent_id !== null
                ? ($cats->firstWhere('id', $c->parent_id)->name ?? '').' / '.$c->name : $c->name])
            ->sortBy('name')->values()->all());
    }
}
