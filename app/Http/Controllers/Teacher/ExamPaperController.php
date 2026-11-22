<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\M18\ExamPapers;
use App\Enums\M04\QuestionStatus;
use App\Enums\M04\QuizKind;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M03\CourseAuthor;
use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\M04\Quiz;
use App\Models\M04\QuizQuestion;
use App\Models\User;
use App\Support\M03\AuthoringScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-GV-13: đề thi thử của Giáo viên, câu lấy từ ngân hàng bảo mật của khóa được giao (QT-19 a1–a2). */
final class ExamPaperController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->teacher($request);
        $courseIds = CourseAuthor::query()->where('user_id', $user->id)->whereNull('ended_at')->pluck('course_id');
        $banks = QuestionBank::query()->whereIn('course_id', $courseIds)->where('is_confidential', true)->with('course:id,title')->orderBy('name')->get();

        return Inertia::render('teacher/exam-papers/index', [
            'papers' => Quiz::query()->where('kind', QuizKind::ThiThu)->where('owner_teacher_id', $user->id)->withCount(['items' => fn ($q) => $q->whereNull('removed_at')])
                ->orderByDesc('id')->get()->map(fn (Quiz $q) => [
                    'id' => $q->id, 'title' => $q->title, 'question_count' => $q->items_count, 'locked' => $q->locked_at !== null,
                    'answers_open' => $q->answers_open_at !== null, 'edit_url' => route('teacher.exam-papers.edit', $q),
                ])->all(),
            'banks' => $banks->map(fn (QuestionBank $b) => ['id' => $b->id, 'name' => $b->name, 'course' => $b->course?->title,
                'create_url' => route('teacher.exam-papers.create', ['bank' => $b->id])])->all(),
            'exams_url' => route('teacher.exams.index'),
        ]);
    }

    public function create(Request $request): Response
    {
        $bank = QuestionBank::query()->findOrFail($request->integer('bank'));
        $this->guardBank($this->teacher($request), $bank);

        return $this->form($bank, null);
    }

    public function store(Request $request, ExamPapers $papers): RedirectResponse
    {
        $data = $this->validated($request, true);
        $quiz = $papers->create($this->teacher($request), QuestionBank::query()->findOrFail($data['bank_id']), $data['title'], $data['question_ids']);

        return to_route('teacher.exam-papers.edit', $quiz)->with('status', 'Đã tạo đề.');
    }

    public function edit(Request $request, Quiz $quiz): Response
    {
        $user = $this->teacher($request);
        abort_unless($quiz->kind === QuizKind::ThiThu && $quiz->owner_teacher_id === $user->id, 403);
        $bank = QuestionBank::query()->findOrFail($quiz->question_bank_id);

        return $this->form($bank, $quiz);
    }

    public function update(Request $request, Quiz $quiz, ExamPapers $papers): RedirectResponse
    {
        $data = $this->validated($request, false);
        $papers->update($this->teacher($request), $quiz, $data['title'], $data['question_ids']);

        return back()->with('status', 'Đã lưu đề.');
    }

    private function form(QuestionBank $bank, ?Quiz $quiz): Response
    {
        $selected = $quiz === null ? collect() : QuizQuestion::query()->where('quiz_id', $quiz->id)->whereNull('removed_at')->pluck('question_id');

        return Inertia::render('teacher/exam-papers/form', [
            'bank' => ['id' => $bank->id, 'name' => $bank->name, 'course' => Course::query()->whereKey($bank->course_id)->value('title')],
            'paper' => $quiz === null ? null : ['id' => $quiz->id, 'title' => $quiz->title, 'locked' => $quiz->locked_at !== null],
            'questions' => Question::query()->where('question_bank_id', $bank->id)->where('status', QuestionStatus::DangDung)->orderBy('id')->get()
                ->map(fn (Question $q) => ['id' => $q->id, 'stem' => mb_substr(strip_tags($q->stem), 0, 200), 'type' => $q->type->label(),
                    'difficulty' => $q->difficulty->label(), 'points' => (string) $q->points])->all(),
            'selected' => $selected->all(),
            'submit_url' => $quiz === null ? route('teacher.exam-papers.store') : route('teacher.exam-papers.update', $quiz),
            'index_url' => route('teacher.exam-papers.index'),
        ]);
    }

    /** @return array{bank_id: int, title: string, question_ids: list<int>} */
    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'bank_id' => [$creating ? 'required' : 'nullable', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'question_ids' => ['required', 'array', 'min:1', 'max:200'],
            'question_ids.*' => ['integer'],
        ]);

        return ['bank_id' => (int) ($data['bank_id'] ?? 0), 'title' => $data['title'], 'question_ids' => array_values(array_map('intval', $data['question_ids']))];
    }

    private function guardBank(User $user, QuestionBank $bank): void
    {
        $course = Course::query()->findOrFail($bank->course_id);
        abort_unless(AuthoringScope::isCurrentAuthor($user, $course) && $bank->is_confidential, 403);
    }

    private function teacher(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
