<?php

namespace App\Http\Controllers\Academic;

use App\Actions\M03\AssignCourseAuthoring;
use App\Actions\M03\AttachCourseTags;
use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M03\ReassignCourseAuthor;
use App\Actions\M03\SetCoursePrerequisite;
use App\Actions\M03\UpdateCourse;
use App\Actions\M03\UpdateCoursePricing;
use App\Enums\M01\RoleCode;
use App\Enums\M03\LearningMode;
use App\Http\Controllers\Controller;
use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\M02\Tag;
use App\Models\M03\Course;
use App\Models\M05\CourseVersion;
use App\Models\User;
use App\Queries\M03\CourseList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** MH-GVU-03/04: khóa học của Giáo vụ — tạo và giao soạn, sửa trường, giá, thẻ, giao lại, trạng thái bán. */
class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Course::class);

        return Inertia::render('academic/courses/index', [
            'courses' => CourseList::forAcademic((string) $request->query('q', '')),
            'filters' => ['q' => (string) $request->query('q', '')],
            'options' => self::options(),
        ]);
    }

    public function store(Request $request, AssignCourseAuthoring $action): RedirectResponse
    {
        Gate::authorize('create', Course::class);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer'],
            'level_id' => ['required', 'integer'],
            'learning_mode' => ['required', Rule::enum(LearningMode::class)],
            'price' => ['required', 'integer', 'min:0'],
            'original_price' => ['nullable', 'integer', 'min:0'],
            'access_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'extension_price' => ['nullable', 'integer', 'min:0'],
            'author_id' => ['required', 'integer'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $actor = $request->user();
        $course = $action->handle($actor, [
            'title' => (string) $data['title'],
            'category_id' => (int) $data['category_id'],
            'level_id' => (int) $data['level_id'],
            'learning_mode' => LearningMode::from($data['learning_mode']),
            'price' => (int) $data['price'],
            'original_price' => self::int($data['original_price'] ?? null),
            'access_months' => self::int($data['access_months'] ?? null),
            'extension_price' => self::int($data['extension_price'] ?? null),
            'author_id' => (int) $data['author_id'],
            'due_date' => $data['due_date'] ?? null,
        ]);

        return redirect()->route('academic.courses.show', $course)->with('status', 'Đã tạo khóa và giao soạn.');
    }

    public function show(Course $course): Response
    {
        Gate::authorize('view', $course);
        $course->load(['category', 'level', 'tags', 'currentAuthor.user']);

        return Inertia::render('academic/courses/show', [
            'course' => CourseList::detail($course),
            'versions' => CourseVersion::query()->where('course_id', $course->id)->orderByDesc('version_no')->get()->map(fn (CourseVersion $v) => [
                'id' => $v->id, 'version_no' => $v->version_no, 'status' => $v->status->value, 'status_label' => $v->status->label(),
                'submitted_at' => $v->submitted_at?->toIso8601String(), 'published_at' => $v->published_at?->toIso8601String(),
            ])->values(),
            'options' => self::options(),
            // CR-67 (P6): một khóa tiên quyết; chỉ chặn ghi danh chưa bắt đầu học.
            'prerequisite' => [
                'id' => $course->prerequisite_course_id,
                'options' => Course::query()->whereKeyNot($course->id)->orderBy('title')->get(['id', 'title'])->map(fn (Course $c) => ['id' => $c->id, 'title' => $c->title])->all(),
                'url' => route('academic.courses.prerequisite', $course),
            ],
        ]);
    }

    public function prerequisite(Request $request, Course $course, SetCoursePrerequisite $action): RedirectResponse
    {
        Gate::authorize('view', $course);
        $data = $request->validate(['prerequisite_course_id' => ['nullable', 'integer']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($actor, $course, isset($data['prerequisite_course_id']) ? (int) $data['prerequisite_course_id'] : null);

        return back()->with('status', 'Đã cập nhật khóa tiên quyết.');
    }

    public function update(Request $request, Course $course, UpdateCourse $action): RedirectResponse
    {
        Gate::authorize('update', $course);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'category_id' => ['sometimes', 'required', 'integer'],
            'level_id' => ['sometimes', 'required', 'integer'],
            'learning_mode' => ['sometimes', 'required', Rule::enum(LearningMode::class)],
            'access_months' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        if (isset($data['learning_mode'])) {
            $data['learning_mode'] = LearningMode::from($data['learning_mode']);
        }
        $actor = $request->user();
        $action->handle($actor, $course, $data);

        return back()->with('status', 'Đã lưu thông tin khóa.');
    }

    public function pricing(Request $request, Course $course, UpdateCoursePricing $action): RedirectResponse
    {
        Gate::authorize('updatePricing', $course);
        $data = $request->validate([
            'price' => ['required', 'integer', 'min:0'],
            'original_price' => ['nullable', 'integer', 'min:0'],
            'extension_price' => ['nullable', 'integer', 'min:0'],
        ]);
        $actor = $request->user();
        $action->handle($actor, $course, (int) $data['price'], self::int($data['original_price'] ?? null), self::int($data['extension_price'] ?? null));

        return back()->with('status', 'Đã lưu giá.');
    }

    public function tags(Request $request, Course $course, AttachCourseTags $action): RedirectResponse
    {
        Gate::authorize('attachTags', $course);
        $data = $request->validate(['tag_ids' => ['present', 'array'], 'tag_ids.*' => ['integer']]);
        $actor = $request->user();
        $action->handle($actor, $course, array_values(array_map('intval', $data['tag_ids'])));

        return back()->with('status', 'Đã lưu thẻ.');
    }

    public function reassign(Request $request, Course $course, ReassignCourseAuthor $action): RedirectResponse
    {
        Gate::authorize('reassignAuthor', $course);
        $data = $request->validate(['author_id' => ['required', 'integer'], 'due_date' => ['nullable', 'date_format:Y-m-d']]);
        $actor = $request->user();
        $action->handle($actor, $course, (int) $data['author_id'], $data['due_date'] ?? null);

        return back()->with('status', 'Đã giao lại người soạn.');
    }

    public function sell(Request $request, Course $course, ChangeSaleStatus $action): RedirectResponse
    {
        Gate::authorize('startSelling', $course);
        $actor = $request->user();
        $action->start($actor, $course);

        return back()->with('status', 'Đã bật bán.');
    }

    public function stopSelling(Request $request, Course $course, ChangeSaleStatus $action): RedirectResponse
    {
        Gate::authorize('stopSelling', $course);
        $actor = $request->user();
        $action->stop($actor, $course);

        return back()->with('status', 'Đã ngừng bán.');
    }

    public function resumeSelling(Request $request, Course $course, ChangeSaleStatus $action): RedirectResponse
    {
        Gate::authorize('resumeSelling', $course);
        $actor = $request->user();
        $action->resume($actor, $course);

        return back()->with('status', 'Đã bán lại.');
    }

    /** @return array<string, mixed> */
    public static function options(): array
    {
        $cats = Category::query()->orderBy('position')->orderBy('name')->get();

        return [
            'categories' => $cats->whereNotNull('parent_id')->map(fn (Category $c) => [
                'id' => $c->id, 'name' => ($cats->firstWhere('id', $c->parent_id)->name ?? '').' / '.$c->name,
            ])->merge($cats->whereNull('parent_id')->filter(fn (Category $c) => $cats->where('parent_id', $c->id)->isEmpty())
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name]))
                ->sortBy('name')->values(),
            'levels' => Level::query()->orderBy('position')->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
            'teachers' => User::query()->whereHas('roles', fn ($q) => $q->where('code', RoleCode::GiaoVien->value))
                ->where('status', 'HOAT_DONG')->orderBy('name')->get(['id', 'name', 'email']),
            'learningModes' => array_map(fn (LearningMode $m) => ['value' => $m->value, 'label' => $m->label()], LearningMode::cases()),
        ];
    }

    private static function int(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }
}
