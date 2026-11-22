<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\M05\CreateDraftVersion;
use App\Actions\M05\EditDraftContent;
use App\Actions\M05\VersionTransitions;
use App\Enums\M04\QuestionStatus;
use App\Enums\M05\CourseVersionStatus;
use App\Enums\M05\LessonType;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\M05\CourseSection;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Queries\M03\CourseList;
use App\Queries\M05\VersionTree;
use App\Support\M05\DraftChecklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** MH-GV-03..06: "Khóa tôi soạn", trình soạn bản nháp, gửi duyệt, rút về nháp, bắt đầu sửa, tạo bản nháp mới. */
class CourseEditorController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();

        return Inertia::render('teacher/courses/index', ['courses' => CourseList::forAuthor($actor)]);
    }

    public function show(Request $request, Course $course): Response
    {
        Gate::authorize('view', $course);
        $actor = $request->user();
        $course->load(['category', 'level', 'tags', 'currentAuthor.user']);
        /** @var CourseVersion|null $version */
        $version = $course->openVersion()->first() ?? $course->currentVersion;
        $banks = QuestionBank::query()->where('course_id', $course->id)->where('is_confidential', false)->orderBy('name')->get();

        return Inertia::render('teacher/courses/edit', [
            'course' => CourseList::detail($course),
            'version' => $version === null ? null : [
                'id' => $version->id, 'version_no' => $version->version_no,
                'status' => $version->status->value, 'status_label' => $version->status->label(),
            ],
            'sections' => $version !== null ? VersionTree::sections($version) : [],
            'reviews' => $version !== null ? VersionTree::reviews($version) : [],
            'checklist' => $version !== null && $version->status === CourseVersionStatus::Nhap ? DraftChecklist::evaluate($version) : [],
            'bankQuestions' => $banks->map(fn (QuestionBank $b) => [
                'id' => $b->id, 'name' => $b->name,
                'questions' => Question::query()->where('question_bank_id', $b->id)->where('status', QuestionStatus::DangDung->value)
                    ->orderBy('id')->get(['id', 'stem', 'difficulty'])->map(fn (Question $q) => ['id' => $q->id, 'stem' => $q->stem])->values(),
            ])->values(),
            'can' => [
                'edit' => $version !== null && $version->status === CourseVersionStatus::Nhap && $actor->can('edit', $version),
                'submit' => $version !== null && $version->status === CourseVersionStatus::Nhap && $actor->can('submit', $version),
                'withdraw' => $version !== null && $version->status === CourseVersionStatus::ChoDuyet && $actor->can('withdraw', $version),
                'reopen' => $version !== null && $version->status === CourseVersionStatus::TraLai && $actor->can('reopen', $version),
                'createDraft' => $version !== null && $version->status === CourseVersionStatus::DaCongBo && $actor->can('createDraftVersion', $course),
                'preview' => $version !== null && $actor->can('preview', $version),
            ],
            'lessonTypes' => array_map(fn (LessonType $t) => ['value' => $t->value, 'label' => $t->label()], LessonType::cases()),
        ]);
    }

    public function createDraft(Request $request, Course $course, CreateDraftVersion $action): RedirectResponse
    {
        Gate::authorize('createDraftVersion', $course);
        $actor = $request->user();
        $result = $action->handle($actor, $course);
        $msg = 'Đã tạo bản nháp '.$result['version']->version_no.'.';
        if ($result['dropped'] !== []) {
            $msg .= ' Câu đã ngừng dùng bị bỏ khỏi quiz: '.implode('; ', array_map(fn ($d) => $d['lesson'].' – '.$d['stem'], $result['dropped'])).'.';
        }

        return back()->with('status', $msg);
    }

    public function cover(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('updateCover', $course);
        $request->validate(['cover' => ['required', 'image', 'max:5120']]);
        $path = $request->file('cover')?->store('course-covers', 'public');
        $course->forceFill(['cover_path' => is_string($path) ? $path : null])->save();

        return back()->with('status', 'Đã lưu ảnh bìa.');
    }

    public function addSection(Request $request, CourseVersion $version, EditDraftContent $action): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200']]);
        $actor = $request->user();
        $action->addSection($actor, $version->id, $data['title']);

        return back();
    }

    public function updateSection(Request $request, CourseSection $section, EditDraftContent $action): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200']]);
        $actor = $request->user();
        $action->renameSection($actor, $section->id, $data['title']);

        return back();
    }

    public function addLesson(Request $request, CourseSection $section, EditDraftContent $action): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::enum(LessonType::class)],
        ]);
        $actor = $request->user();
        $action->addLesson($actor, $section->id, $data['title'], LessonType::from($data['type']));

        return back();
    }

    public function updateLesson(Request $request, Lesson $lesson, EditDraftContent $action): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'objectives' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'content' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'is_required' => ['sometimes', 'boolean'],
            'is_preview' => ['sometimes', 'boolean'],
            'is_final_test' => ['sometimes', 'boolean'],
        ]);
        foreach (['is_required', 'is_preview', 'is_final_test'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $data[$flag] = (bool) $data[$flag];
            }
        }
        $actor = $request->user();
        $action->saveLesson($actor, $lesson->id, $data);

        return back();
    }

    public function move(Request $request, string $kind, int $id, EditDraftContent $action): RedirectResponse
    {
        abort_unless(in_array($kind, ['section', 'lesson'], true), 404);
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $actor = $request->user();
        $action->move($actor, $kind, $id, $data['direction']);

        return back();
    }

    public function remove(Request $request, string $kind, int $id, EditDraftContent $action): RedirectResponse
    {
        abort_unless(in_array($kind, ['section', 'lesson', 'resource'], true), 404);
        $actor = $request->user();
        $action->remove($actor, $kind, $id);

        return back()->with('status', 'Đã gỡ khỏi bản nháp.');
    }

    public function video(Request $request, Lesson $lesson, EditDraftContent $action): RedirectResponse
    {
        // A14 (BR-ND-87): MP4 tối đa 500 MB.
        $request->validate(['video' => ['required', 'file', 'extensions:mp4', 'mimetypes:video/mp4', 'max:512000']], [
            'video.max' => 'Tệp MP4 tối đa 500 MB.',
        ]);
        $actor = $request->user();
        $file = $request->file('video');
        abort_if($file === null, 422);
        $action->uploadVideo($actor, $lesson->id, $file);

        return back()->with('status', 'Đã nhận video. Hệ thống đang đọc thời lượng.');
    }

    public function pdf(Request $request, Lesson $lesson, EditDraftContent $action): RedirectResponse
    {
        $request->validate(['pdf' => ['required', 'file', 'extensions:pdf', 'mimetypes:application/pdf', 'max:51200']], [
            'pdf.max' => 'Tệp PDF tối đa 50 MB.',
        ]);
        $actor = $request->user();
        $file = $request->file('pdf');
        abort_if($file === null, 422);
        $action->uploadPdf($actor, $lesson->id, $file);

        return back()->with('status', 'Đã nhận tệp PDF.');
    }

    public function resource(Request $request, Lesson $lesson, EditDraftContent $action): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:51200'], 'title' => ['nullable', 'string', 'max:200']]);
        $actor = $request->user();
        $file = $request->file('file');
        abort_if($file === null, 422);
        $action->addResource($actor, $lesson->id, $file, $request->string('title')->toString());

        return back();
    }

    public function quiz(Request $request, Lesson $lesson, EditDraftContent $action): RedirectResponse
    {
        // CR-19: điểm đạt 1–100%, thời lượng 1–180 phút, số lượt 1–10; bản nháp lưu được khi chưa đủ.
        $data = $request->validate([
            'pass_percent' => ['nullable', 'integer', 'between:1,100'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,180'],
            'max_attempts' => ['nullable', 'integer', 'between:1,10'],
        ]);
        $actor = $request->user();
        $action->saveQuiz($actor, $lesson->id, self::int($data['pass_percent'] ?? null), self::int($data['duration_minutes'] ?? null), self::int($data['max_attempts'] ?? null));

        return back();
    }

    public function quizQuestions(Request $request, Lesson $lesson, EditDraftContent $action): RedirectResponse
    {
        $data = $request->validate(['question_ids' => ['present', 'array'], 'question_ids.*' => ['integer']]);
        $actor = $request->user();
        $action->setQuizQuestions($actor, $lesson->id, array_values(array_map('intval', $data['question_ids'])));

        return back();
    }

    public function submit(Request $request, CourseVersion $version, VersionTransitions $action): RedirectResponse
    {
        Gate::authorize('submit', $version);
        $actor = $request->user();
        $action->submit($actor, $version);

        return back()->with('status', 'Đã gửi duyệt.');
    }

    public function withdraw(Request $request, CourseVersion $version, VersionTransitions $action): RedirectResponse
    {
        Gate::authorize('withdraw', $version);
        $actor = $request->user();
        $action->withdraw($actor, $version);

        return back()->with('status', 'Đã rút về nháp.');
    }

    public function reopen(Request $request, CourseVersion $version, VersionTransitions $action): RedirectResponse
    {
        Gate::authorize('reopen', $version);
        $actor = $request->user();
        $action->reopen($actor, $version);

        return back()->with('status', 'Bản đã về nháp, bạn có thể sửa.');
    }

    private static function int(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }
}
