<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\M04\ConfidentialBanks;
use App\Actions\M04\ImportQuestions;
use App\Actions\M04\QuestionBankActions;
use App\Actions\M25\AiQuestions;
use App\Enums\M04\QuestionDifficulty;
use App\Enums\M04\QuestionStatus;
use App\Enums\M04\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\M02\Tag;
use App\Models\M03\Course;
use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\M11\Setting;
use App\Support\M25\AiProvider;
use App\Support\M25\FakeAiProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** MH-GV-07/08: ngân hàng câu hỏi của khóa (QT-04), lọc trên URL (AC-04-60). */
class QuestionBankController extends Controller
{
    private const L_TYPES = [QuestionType::MotDapAn, QuestionType::NhieuDapAn, QuestionType::DungSai, QuestionType::DienTu, QuestionType::SapXep];

    public function index(Course $course): Response
    {
        Gate::authorize('manageQuestionBanks', $course);

        return Inertia::render('teacher/question-banks/index', [
            'course' => ['id' => $course->id, 'title' => $course->title],
            'banks' => QuestionBank::query()->where('course_id', $course->id)->withCount('questions')->orderBy('name')->get()
                ->map(fn (QuestionBank $b) => ['id' => $b->id, 'name' => $b->name, 'questions_count' => $b->getAttribute('questions_count'), 'is_confidential' => $b->is_confidential])->values(),
        ]);
    }

    public function store(Request $request, Course $course, QuestionBankActions $action): RedirectResponse
    {
        Gate::authorize('manageQuestionBanks', $course);
        $data = $request->validate(['name' => ['required', 'string', 'max:150']]);
        $actor = $request->user();
        $bank = $action->createBank($actor, $course, $data['name']);

        return redirect()->route('teacher.question-banks.show', $bank)->with('status', 'Đã tạo ngân hàng.');
    }

    public function show(Request $request, QuestionBank $bank, AiProvider $provider): Response
    {
        Gate::authorize('view', $bank);
        $filters = [
            'type' => (string) $request->query('type', ''),
            'difficulty' => (string) $request->query('difficulty', ''),
            'tag' => (string) $request->query('tag', ''),
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
        ];
        $questions = Question::query()->with(['tags:id,name', 'options'])->where('question_bank_id', $bank->id)
            ->when(QuestionType::tryFrom($filters['type']), fn ($q, $t) => $q->where('type', $t->value))
            ->when(QuestionDifficulty::tryFrom($filters['difficulty']), fn ($q, $d) => $q->where('difficulty', $d->value))
            ->when(QuestionStatus::tryFrom($filters['status']), fn ($q, $s) => $q->where('status', $s->value))
            ->when($filters['tag'] !== '', fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey((int) $filters['tag'])))
            ->when($filters['q'] !== '', fn ($q) => $q->where('stem', 'like', '%'.$filters['q'].'%'))
            ->orderBy('id')
            ->paginate(20)->withQueryString()
            ->through(fn (Question $q) => [
                'id' => $q->id,
                'type' => $q->type->value,
                'type_label' => $q->type->label(),
                'difficulty' => $q->difficulty->value,
                'difficulty_label' => $q->difficulty->label(),
                'stem' => $q->stem,
                'points' => (string) $q->points,
                'hint' => $q->hint,
                'explanation' => $q->explanation,
                'status' => $q->status->value,
                'status_label' => $q->status->label(),
                'source' => $q->source->value,
                'ai_ever_approved' => $q->ai_first_approved_at !== null,
                'image' => $q->image_path !== null ? asset('storage/'.$q->image_path) : null,
                'tag_ids' => $q->tags->pluck('id')->values(),
                'tags' => $q->tags->pluck('name')->values(),
                'options' => $q->options->map(fn ($o) => ['content' => $o->content, 'is_correct' => $o->is_correct])->values(),
            ]);

        return Inertia::render('teacher/question-banks/show', [
            'course' => ['id' => $bank->course_id, 'title' => $bank->course->title],
            'bank' => ['id' => $bank->id, 'name' => $bank->name, 'is_confidential' => $bank->is_confidential,
                'import_url' => route('teacher.question-banks.import', $bank), 'template_url' => route('teacher.question-banks.template'),
                'confidential_url' => route('teacher.question-banks.confidential', $bank)],
            'import_errors' => $request->session()->get('import.errors', []),
            // QT-27a: AI gợi ý câu từ bài của bản mới nhất; ngân hàng bảo mật không dùng AI (E38).
            'ai' => [
                'enabled' => Setting::valueFor('ai.enabled') === true && ! $bank->is_confidential,
                'max' => AiQuestions::MAX_PER_REQUEST,
                // UAT 22/11: cho giáo viên biết đang dùng AI thật hay bộ trích xuất tự động (chưa có khóa Gemini).
                'simulated' => $provider instanceof FakeAiProvider,
                'generate_url' => route('teacher.question-banks.ai.generate', $bank),
                'lessons' => Lesson::query()->whereNull('removed_at')
                    ->where('course_version_id', CourseVersion::query()->where('course_id', $bank->course_id)->max('id'))
                    ->orderBy('course_section_id')->orderBy('position')->get(['id', 'title'])
                    ->map(fn (Lesson $l) => ['id' => $l->id, 'name' => $l->title])->values(),
            ],
            'questions' => $questions,
            'filters' => $filters,
            'options' => [
                'types' => array_map(fn (QuestionType $t) => ['value' => $t->value, 'label' => $t->label()], self::L_TYPES),
                'difficulties' => array_map(fn (QuestionDifficulty $d) => ['value' => $d->value, 'label' => $d->label()], QuestionDifficulty::cases()),
                'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function update(Request $request, QuestionBank $bank, QuestionBankActions $action): RedirectResponse
    {
        Gate::authorize('update', $bank);
        $data = $request->validate(['name' => ['required', 'string', 'max:150']]);
        $actor = $request->user();
        $action->renameBank($actor, $bank, $data['name']);

        return back()->with('status', 'Đã đổi tên ngân hàng.');
    }

    /** M04 (N), CR-68: nhập câu từ Excel/CSV; có lỗi thì không nhập dòng nào và trả danh sách lỗi theo dòng. */
    public function import(Request $request, QuestionBank $bank, ImportQuestions $import): RedirectResponse
    {
        Gate::authorize('createQuestion', $bank);
        $data = $request->validate(['file' => ['required', 'file', 'max:5120']]);
        $result = $import->handle($request->user(), $bank, $data['file']);
        if ($result['errors'] !== []) {
            return back()->with('import.errors', $result['errors']);
        }

        return back()->with('status', 'Đã nhập '.$result['imported'].' câu hỏi.');
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            IOFactory::createWriter(ImportQuestions::template(), 'Xlsx')->save('php://output');
        }, 'mau-nhap-cau-hoi.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** BR-ND-63/69/70: bật hoặc tắt cờ bảo mật của ngân hàng. */
    public function confidential(Request $request, QuestionBank $bank, ConfidentialBanks $banks): RedirectResponse
    {
        Gate::authorize('update', $bank);
        $data = $request->validate(['is_confidential' => ['required', 'boolean']]);
        $banks->set($request->user(), $bank, (bool) $data['is_confidential']);

        return back()->with('status', $data['is_confidential'] ? 'Đã bật bảo mật cho ngân hàng.' : 'Đã tắt bảo mật của ngân hàng.');
    }

    public function storeQuestion(Request $request, QuestionBank $bank, QuestionBankActions $action): RedirectResponse
    {
        Gate::authorize('createQuestion', $bank);
        $data = $this->validated($request, true);
        $actor = $request->user();
        $action->createQuestion($actor, $bank, [
            ...$data,
            'type' => $data['type'] ?? QuestionType::MotDapAn,
            'stem' => $data['stem'] ?? '',
            'options' => $data['options'] ?? [],
        ], $request->file('image'));

        return back()->with('status', 'Đã thêm câu hỏi.');
    }

    public function updateQuestion(Request $request, Question $question, QuestionBankActions $action): RedirectResponse
    {
        Gate::authorize('update', $question);
        $data = $this->validated($request, false);
        $actor = $request->user();
        $action->updateQuestion($actor, $question, $data, $request->file('image'));

        return back()->with('status', 'Đã lưu câu hỏi.');
    }

    public function deactivate(Request $request, Question $question, QuestionBankActions $action): RedirectResponse
    {
        Gate::authorize('deactivate', $question);
        $actor = $request->user();
        $action->deactivate($actor, $question);

        return back()->with('status', 'Đã ngừng dùng câu.');
    }

    public function reactivate(Request $request, Question $question, QuestionBankActions $action): RedirectResponse
    {
        Gate::authorize('reactivate', $question);
        $actor = $request->user();
        $action->reactivate($actor, $question);

        return back()->with('status', 'Đã dùng lại câu.');
    }

    /**
     * @return array{type?: QuestionType, difficulty?: QuestionDifficulty, stem?: string, points?: float|string|null, hint?: string|null, explanation?: string|null, options?: list<array{content: string, is_correct: bool}>, tag_ids?: list<int>}
     */
    private function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'type' => [$creating ? 'required' : 'prohibited', Rule::enum(QuestionType::class)->only(self::L_TYPES)],
            'difficulty' => ['sometimes', Rule::enum(QuestionDifficulty::class)],
            'stem' => [$req, 'string', 'max:10000'],
            'points' => ['sometimes', 'nullable', 'numeric'],
            'hint' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'explanation' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'options' => [$req, 'array'],
            'options.*.content' => ['required', 'string', 'max:2000'],
            'options.*.is_correct' => ['required', 'boolean'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer'],
            'image' => ['sometimes', 'nullable', 'image', 'max:5120'],
        ]);
        $out = [];
        if (isset($data['type'])) {
            $out['type'] = QuestionType::from((string) $data['type']);
        }
        if (isset($data['difficulty'])) {
            $out['difficulty'] = QuestionDifficulty::from((string) $data['difficulty']);
        }
        if (array_key_exists('stem', $data)) {
            $out['stem'] = (string) $data['stem'];
        }
        if (array_key_exists('points', $data)) {
            $out['points'] = $data['points'] === null ? null : (string) $data['points'];
        }
        foreach (['hint', 'explanation'] as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k] === null ? null : (string) $data[$k];
            }
        }
        if (isset($data['options']) && is_array($data['options'])) {
            $out['options'] = array_values(array_map(fn ($o) => ['content' => (string) $o['content'], 'is_correct' => (bool) $o['is_correct']], $data['options']));
        }
        if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
            $out['tag_ids'] = array_values(array_map('intval', $data['tag_ids']));
        }

        return $out;
    }
}
