<?php

namespace App\Http\Controllers\Exams;

use App\Actions\M18\ExamEvents;
use App\Enums\M01\RoleCode;
use App\Enums\M03\SaleStatus;
use App\Enums\M04\QuizKind;
use App\Enums\M10\AttemptStatus;
use App\Enums\M18\ExamAudience;
use App\Enums\M18\ExamStatus;
use App\Enums\M18\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M03\CourseAuthor;
use App\Models\M04\Quiz;
use App\Models\M10\QuizAttempt;
use App\Models\M18\ExamEvent;
use App\Models\M18\ExamEventCourse;
use App\Models\M18\ExamRanking;
use App\Models\M18\ExamRegistration;
use App\Models\M18\ExamTabLeave;
use App\Models\User;
use App\Queries\M19\QuizAnalytics;
use App\Support\M10\QuizScore;
use App\Support\M19\Xlsx;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MH-GV-14 / MH-GVU-12: người tổ chức tạo, sửa nháp, chuyển `SAP_MO`, dời lịch, hẹn công bố; xem đăng ký, xếp hạng.
 * Rời tab và từng câu chỉ GV người tổ chức xem (CR-146/149); Giáo vụ chỉ thấy tên/số câu/thời lượng đề (CR-75).
 */
final class ManageController extends Controller
{
    public const TZ = 'Asia/Ho_Chi_Minh';

    public function index(Request $request): Response
    {
        $user = $this->organizer($request);
        $p = $this->prefix($request);

        return Inertia::render('exams/manage/index', [
            'scope' => $p,
            'events' => ExamEvent::query()->where('created_by_id', $user->id)->with('quiz:id,title')->orderByDesc('opens_at')->get()
                ->map(fn (ExamEvent $e) => [...self::summary($e), 'url' => route($p.'.exams.show', $e)])->all(),
            'create_url' => route($p.'.exams.create'),
            'papers_url' => $p === 'teacher' ? route('teacher.exam-papers.index') : null,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null);
    }

    public function store(Request $request, ExamEvents $events): RedirectResponse
    {
        $event = $events->create($this->organizer($request), $this->validated($request));

        return to_route($this->prefix($request).'.exams.show', $event)->with('status', 'Đã tạo kỳ thi (Nháp).');
    }

    public function edit(Request $request, ExamEvent $exam): Response
    {
        $this->owned($request, $exam);

        return $this->form($request, $exam);
    }

    public function update(Request $request, ExamEvent $exam, ExamEvents $events): RedirectResponse
    {
        $events->update($this->organizer($request), $exam, $this->validated($request));

        return to_route($this->prefix($request).'.exams.show', $exam)->with('status', 'Đã lưu kỳ thi.');
    }

    public function schedule(Request $request, ExamEvent $exam, ExamEvents $events): RedirectResponse
    {
        $events->schedule($this->organizer($request), $exam);

        return back()->with('status', 'Kỳ thi đã chuyển sang Sắp mở; đề đã được khóa.');
    }

    public function reschedule(Request $request, ExamEvent $exam, ExamEvents $events): RedirectResponse
    {
        $data = $request->validate([
            'opens_at' => ['required', 'date_format:Y-m-d\TH:i'], 'closes_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
        ]);
        $events->reschedule($this->organizer($request), $exam, self::time($data['opens_at']), self::time($data['closes_at']), (int) $data['duration_minutes']);

        return back()->with('status', 'Đã dời lịch kỳ thi.');
    }

    public function publishAt(Request $request, ExamEvent $exam, ExamEvents $events): RedirectResponse
    {
        $data = $request->validate(['publish_at' => ['nullable', 'date_format:Y-m-d\TH:i']]);
        $events->setPublishAt($this->organizer($request), $exam, ($data['publish_at'] ?? null) === null ? null : self::time($data['publish_at']));

        return back()->with('status', 'Đã đặt giờ công bố.');
    }

    public function show(Request $request, ExamEvent $exam): Response
    {
        $user = $this->owned($request, $exam);
        $p = $this->prefix($request);
        $teacher = $p === 'teacher';
        $quiz = Quiz::query()->findOrFail($exam->quiz_id);
        $attempts = QuizAttempt::query()->where('exam_event_id', $exam->id)->with('user:id,name')->orderBy('id')->get();
        $rankings = ExamRanking::query()->where('exam_event_id', $exam->id)->orderBy('rank')->orderBy('id')->get()->keyBy('quiz_attempt_id');
        $leaves = $teacher ? ExamTabLeave::query()->whereIn('quiz_attempt_id', $attempts->pluck('id'))->selectRaw('quiz_attempt_id, COUNT(*) AS n, SUM(duration_ms) AS ms')
            ->groupBy('quiz_attempt_id')->toBase()->get()->keyBy('quiz_attempt_id') : collect();
        $registered = ExamRegistration::query()->where('exam_event_id', $exam->id)->where('status', RegistrationStatus::DaDangKy)->count();
        $analytics = $teacher && $quiz->owner_teacher_id === $user->id
            ? QuizAnalytics::build($quiz, QuizAttempt::query()->where('exam_event_id', $exam->id), $registered) : null;

        return Inertia::render('exams/manage/show', [
            'scope' => $p,
            'event' => [
                ...self::summary($exam),
                'courses' => Course::query()->whereIn('id', ExamEventCourse::query()->where('exam_event_id', $exam->id)->select('course_id'))->pluck('title')->all(),
                'opens_input' => $exam->opens_at->setTimezone(self::TZ)->format('Y-m-d\TH:i'),
                'closes_input' => $exam->closes_at->setTimezone(self::TZ)->format('Y-m-d\TH:i'),
                'publish_input' => $exam->publish_at?->setTimezone(self::TZ)->format('Y-m-d\TH:i'),
                'question_count' => $quiz->items()->whereNull('removed_at')->count(),
                'registered' => $registered,
            ],
            'actions' => [
                'edit_url' => $exam->status === ExamStatus::Nhap ? route($p.'.exams.edit', $exam) : null,
                'schedule_url' => $exam->status === ExamStatus::Nhap ? route($p.'.exams.schedule', $exam) : null,
                'reschedule_url' => $exam->status === ExamStatus::SapMo && now()->lt($exam->opens_at) ? route($p.'.exams.reschedule', $exam) : null,
                'publish_at_url' => $exam->status !== ExamStatus::DaCongBo ? route($p.'.exams.publish-at', $exam) : null,
                'export_url' => $analytics !== null ? route('teacher.exams.export', $exam) : null,
                'room_url' => route('exams.show', $exam),
                'index_url' => route($p.'.exams.index'),
            ],
            'participants' => $attempts->map(function (QuizAttempt $a) use ($rankings, $leaves, $teacher) {
                $rank = $rankings->get($a->id);
                $leave = $leaves->get($a->id);

                return [
                    'id' => $a->id, 'name' => $a->user?->name, 'status' => $a->status->label(), 'reason' => $a->close_reason?->label(),
                    'percent' => $a->status === AttemptStatus::DangLam ? null : QuizScore::display(QuizScore::ratio($a)),
                    'rank' => $rank?->rank, 'ranked' => $rank !== null,
                    'started_at' => $a->started_at->toIso8601String(),
                    'tab_leaves' => $teacher ? (int) ($leave->n ?? 0) : null,
                    'tab_leave_seconds' => $teacher ? intdiv((int) ($leave->ms ?? 0), 1000) : null,
                ];
            })->all(),
            'analytics' => $analytics,
        ]);
    }

    /** QT-20: xuất Excel phân tích kỳ thi — chỉ GV soạn đề và tổ chức kỳ (CR-149). */
    public function export(Request $request, ExamEvent $exam): StreamedResponse
    {
        $user = $this->owned($request, $exam);
        $quiz = Quiz::query()->findOrFail($exam->quiz_id);
        abort_unless($quiz->owner_teacher_id === $user->id, 403);
        $registered = ExamRegistration::query()->where('exam_event_id', $exam->id)->where('status', RegistrationStatus::DaDangKy)->count();

        return Xlsx::download('ky-thi-'.$exam->id.'.xlsx', QuizAnalytics::sheets(QuizAnalytics::build($quiz, QuizAttempt::query()->where('exam_event_id', $exam->id), $registered)));
    }

    private function form(Request $request, ?ExamEvent $exam): Response
    {
        $user = $this->organizer($request);
        $p = $this->prefix($request);
        $papers = Quiz::query()->where('kind', QuizKind::ThiThu)->whereNull('answers_open_at')
            ->when($p === 'teacher', fn ($q) => $q->where('owner_teacher_id', $user->id))
            ->withCount(['items' => fn ($q) => $q->whereNull('removed_at')])->orderByDesc('id')->get();
        $courses = $p === 'teacher'
            ? Course::query()->whereIn('id', CourseAuthor::query()->where('user_id', $user->id)->whereNull('ended_at')->select('course_id'))->orderBy('title')->get(['id', 'title'])
            : Course::query()->where('sale_status', SaleStatus::DangBan)->orderBy('title')->get(['id', 'title']);

        return Inertia::render('exams/manage/form', [
            'scope' => $p,
            'papers' => $papers->map(fn (Quiz $q) => ['id' => $q->id, 'title' => $q->title, 'question_count' => $q->items_count])->all(),
            'courses' => $courses->map(fn (Course $c) => ['id' => $c->id, 'title' => $c->title])->all(),
            'event' => $exam === null ? null : [
                'title' => $exam->title, 'quiz_id' => $exam->quiz_id, 'duration_minutes' => $exam->duration_minutes,
                'opens_at' => $exam->opens_at->setTimezone(self::TZ)->format('Y-m-d\TH:i'),
                'closes_at' => $exam->closes_at->setTimezone(self::TZ)->format('Y-m-d\TH:i'),
                'publish_at' => $exam->publish_at?->setTimezone(self::TZ)->format('Y-m-d\TH:i'),
                'audience' => $exam->audience->value, 'requires_registration' => $exam->requires_registration,
                'course_ids' => ExamEventCourse::query()->where('exam_event_id', $exam->id)->pluck('course_id')->all(),
            ],
            'submit_url' => $exam === null ? route($p.'.exams.store') : route($p.'.exams.update', $exam),
            'method' => $exam === null ? 'post' : 'put',
            'index_url' => route($p.'.exams.index'),
        ]);
    }

    /** @return array{title: string, quiz_id: int, opens_at: CarbonImmutable, closes_at: CarbonImmutable, duration_minutes: int, audience: ExamAudience, requires_registration: bool, course_ids: list<int>, publish_at: ?CarbonImmutable} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'quiz_id' => ['required', 'integer'],
            'opens_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'closes_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'audience' => ['required', 'in:CONG_KHAI,KHOA'],
            'requires_registration' => ['boolean'],
            'course_ids' => ['array'],
            'course_ids.*' => ['integer'],
            'publish_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ]);

        return [
            'title' => $data['title'], 'quiz_id' => (int) $data['quiz_id'],
            'opens_at' => self::time($data['opens_at']), 'closes_at' => self::time($data['closes_at']),
            'duration_minutes' => (int) $data['duration_minutes'], 'audience' => ExamAudience::from($data['audience']),
            'requires_registration' => (bool) ($data['requires_registration'] ?? false),
            'course_ids' => array_values(array_map('intval', $data['course_ids'] ?? [])),
            'publish_at' => ($data['publish_at'] ?? null) === null ? null : self::time($data['publish_at']),
        ];
    }

    /** @return array<string, mixed> */
    public static function summary(ExamEvent $e): array
    {
        return [
            'id' => $e->id, 'title' => $e->title, 'status' => $e->status->value, 'status_label' => $e->status->label(),
            'paper' => $e->quiz?->title, 'opens_at' => $e->opens_at->toIso8601String(), 'closes_at' => $e->closes_at->toIso8601String(),
            'duration_minutes' => $e->duration_minutes, 'audience' => $e->audience->label(), 'requires_registration' => $e->requires_registration,
            'publish_at' => $e->publish_at?->toIso8601String(), 'published_at' => $e->published_at?->toIso8601String(),
        ];
    }

    private static function time(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, self::TZ)?->utc()->startOfMinute() ?? abort(422);
    }

    private function prefix(Request $request): string
    {
        return str_starts_with((string) $request->route()?->getName(), 'academic.') ? 'academic' : 'teacher';
    }

    private function organizer(Request $request): User
    {
        $user = $request->user();
        $role = $this->prefix($request) === 'academic' ? RoleCode::GiaoVu : RoleCode::GiaoVien;
        abort_unless($user instanceof User && $user->hasEffectiveRole($role), 403);

        return $user;
    }

    private function owned(Request $request, ExamEvent $exam): User
    {
        $user = $this->organizer($request);
        abort_unless($exam->created_by_id === $user->id, 403);

        return $user;
    }
}
