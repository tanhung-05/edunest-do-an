<?php

namespace App\Http\Controllers\Exams;

use App\Actions\M18\ExamTaking;
use App\Enums\M01\RoleCode;
use App\Enums\M10\AttemptStatus;
use App\Enums\M18\ExamAudience;
use App\Enums\M18\ExamStatus;
use App\Enums\M18\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\M04\Quiz;
use App\Models\M04\QuizQuestion;
use App\Models\M10\QuizAttempt;
use App\Models\M18\ExamConsent;
use App\Models\M18\ExamEvent;
use App\Models\M18\ExamRanking;
use App\Models\M18\ExamRegistration;
use App\Models\User;
use App\Queries\M10\AttemptDetails;
use App\Queries\M18\ExamBoard;
use App\Support\DomainRejection;
use App\Support\M10\QuizScore;
use App\Support\Tx;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-HV-14/15: danh sách kỳ, đăng ký, phòng thi (đồng hồ máy chủ, ghi rời tab), kết quả, xếp hạng, đáp án (QT-19b). */
final class RoomController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $events = ExamEvent::query()->whereIn('status', [ExamStatus::SapMo, ExamStatus::DangMo, ExamStatus::DaDong, ExamStatus::DaCongBo])
            ->with('quiz:id,title')->orderByRaw("FIELD(status, 'DANG_MO', 'SAP_MO', 'DA_DONG', 'DA_CONG_BO')")->orderBy('opens_at')->limit(100)->get()
            ->filter(fn (ExamEvent $e) => $e->audience === ExamAudience::CongKhai || $e->created_by_id === $user->id
                || ExamTaking::hasDesignatedEnrollment($user, $e, false));
        $regs = ExamRegistration::query()->where('user_id', $user->id)->whereIn('exam_event_id', $events->pluck('id'))->pluck('status', 'exam_event_id');
        $attempts = QuizAttempt::query()->where('user_id', $user->id)->whereIn('exam_event_id', $events->pluck('id'))->pluck('status', 'exam_event_id');

        return Inertia::render('exams/index', [
            'events' => $events->map(fn (ExamEvent $e) => [
                ...ManageController::summary($e),
                'registered' => $regs->get($e->id) === RegistrationStatus::DaDangKy,
                'attempted' => $attempts->has($e->id),
                'url' => route('exams.show', $e),
            ])->values()->all(),
        ]);
    }

    public function show(Request $request, ExamEvent $exam): Response
    {
        $user = $this->user($request);
        $this->visible($user, $exam);
        $now = now();
        $reg = ExamRegistration::query()->where('exam_event_id', $exam->id)->where('user_id', $user->id)->first();
        $attempt = QuizAttempt::query()->where('exam_event_id', $exam->id)->where('user_id', $user->id)->first();
        $registered = $reg?->status === RegistrationStatus::DaDangKy;
        $student = $user->hasEffectiveRole(RoleCode::HocVien);
        $published = $exam->status === ExamStatus::DaCongBo;
        $mine = $attempt === null ? null : ExamRanking::query()->where('quiz_attempt_id', $attempt->id)->first();
        $open = in_array($exam->status, [ExamStatus::SapMo, ExamStatus::DangMo], true) && $now->lt($exam->closes_at);

        return Inertia::render('exams/show', [
            'event' => [
                ...ManageController::summary($exam),
                'question_count' => QuizQuestion::query()->where('quiz_id', $exam->quiz_id)->whereNull('removed_at')->count(),
                'registered_count' => ExamRegistration::query()->where('exam_event_id', $exam->id)->where('status', RegistrationStatus::DaDangKy)->count(),
                'server_time' => $now->toIso8601String(),
            ],
            'me' => [
                'registered' => $registered,
                'unranked' => ExamTaking::unranked($user, $exam),
                'consent' => $student ? (bool) ExamConsent::query()->where('exam_event_id', $exam->id)->where('user_id', $user->id)->value('consent') : null,
                'attempt' => $attempt === null ? null : [
                    'status' => $attempt->status->value, 'status_label' => $attempt->status->label(),
                    'url' => $attempt->status === AttemptStatus::DangLam ? route('exams.attempts.show', $attempt) : route('exams.attempts.result', $attempt),
                ],
                'rank' => $published ? $mine?->rank : null,
                'percent' => $published && $attempt !== null && $attempt->status !== AttemptStatus::DangLam ? QuizScore::display(QuizScore::ratio($attempt)) : null,
            ],
            'actions' => [
                'register_url' => $open && ! $registered && $attempt === null ? route('exams.register', $exam) : null,
                'cancel_url' => $registered && $now->lt($exam->opens_at) ? route('exams.cancel', $exam) : null,
                'consent_url' => $student && ! $published ? route('exams.consent', $exam) : null,
                'start_url' => $exam->status === ExamStatus::DangMo && $now->lt($exam->closes_at) && $attempt === null
                    && (! $exam->requires_registration || $registered) ? route('exams.start', $exam) : null,
            ],
            'ranking' => $published ? ExamBoard::top($exam, $user) : [],
        ]);
    }

    public function register(Request $request, ExamEvent $exam, ExamTaking $taking): RedirectResponse
    {
        $taking->register($this->user($request), $exam);

        return back()->with('status', 'Đã đăng ký kỳ thi.');
    }

    public function cancel(Request $request, ExamEvent $exam, ExamTaking $taking): RedirectResponse
    {
        $taking->cancel($this->user($request), $exam);

        return back()->with('status', 'Đã hủy đăng ký.');
    }

    public function consent(Request $request, ExamEvent $exam, ExamTaking $taking): RedirectResponse
    {
        $data = $request->validate(['consent' => ['required', 'boolean']]);
        $taking->consent($this->user($request), $exam, (bool) $data['consent']);

        return back()->with('status', $data['consent'] ? 'Bạn đồng ý công khai tên nếu vào top 10.' : 'Tên bạn sẽ không được công khai.');
    }

    public function start(Request $request, ExamEvent $exam, ExamTaking $taking): RedirectResponse
    {
        $attempt = $taking->start($this->user($request), $exam);

        return to_route('exams.attempts.show', $attempt);
    }

    public function attempt(Request $request, QuizAttempt $attempt): Response|RedirectResponse
    {
        $user = $this->user($request);
        $attempt = $this->settle($user, $attempt);
        if ($attempt->status !== AttemptStatus::DangLam) {
            return to_route('exams.attempts.result', $attempt);
        }
        $event = ExamEvent::query()->findOrFail($attempt->exam_event_id);

        return Inertia::render('exams/attempt', [
            'event' => ['id' => $event->id, 'title' => $event->title, 'url' => route('exams.show', $event)],
            'attempt' => ['id' => $attempt->id, 'started_at' => $attempt->started_at->toIso8601String(), 'deadline_at' => $attempt->deadline_at->toIso8601String()],
            'questions' => AttemptDetails::questions($attempt, false, fn ($q) => route('exams.attempts.answers.update', ['attempt' => $attempt->id, 'quizQuestion' => $q->id])),
            'submit_url' => route('exams.attempts.submit', $attempt),
            'tab_leave_url' => route('exams.attempts.tab-leave', $attempt),
            'ask_consent' => $user->hasEffectiveRole(RoleCode::HocVien) && $event->status !== ExamStatus::DaCongBo,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function save(Request $request, QuizAttempt $attempt, QuizQuestion $quizQuestion, ExamTaking $taking): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['answer' => ['present', 'nullable']]);
        try {
            $saved = $taking->save($this->user($request), $attempt, $quizQuestion, $data['answer']);
        } catch (DomainRejection $failure) {
            if ($request->expectsJson() || $failure->status !== 422 || $attempt->refresh()->status === AttemptStatus::DangLam) {
                throw $failure;
            }

            return to_route('exams.attempts.result', $attempt)->with('status', $failure->getMessage());
        }

        return $request->expectsJson() ? response()->json(['received_at' => $saved->received_at->toISOString()]) : back();
    }

    public function submit(Request $request, QuizAttempt $attempt, ExamTaking $taking): RedirectResponse
    {
        $data = $request->validate(['consent' => ['nullable', 'boolean']]);
        try {
            $taking->submit($this->user($request), $attempt, array_key_exists('consent', $data) && $data['consent'] !== null ? (bool) $data['consent'] : null);
        } catch (DomainRejection $failure) {
            if ($failure->status !== 422) {
                throw $failure;
            }

            return to_route('exams.attempts.result', $attempt)->with('status', $failure->getMessage());
        }

        return to_route('exams.attempts.result', $attempt)->with('status', 'Đã nộp bài. Kết quả có khi kỳ thi công bố.');
    }

    public function tabLeave(Request $request, QuizAttempt $attempt, ExamTaking $taking): JsonResponse
    {
        $data = $request->validate(['duration_ms' => ['required', 'integer', 'min:0']]);
        $taking->tabLeave($this->user($request), $attempt, (int) $data['duration_ms']);

        return response()->json(['ok' => true]);
    }

    public function result(Request $request, QuizAttempt $attempt): Response|RedirectResponse
    {
        $user = $this->user($request);
        $attempt = $this->settle($user, $attempt);
        if ($attempt->status === AttemptStatus::DangLam) {
            return to_route('exams.attempts.show', $attempt);
        }
        $event = ExamEvent::query()->findOrFail($attempt->exam_event_id);
        $quiz = Quiz::query()->findOrFail($event->quiz_id);
        $published = $event->status === ExamStatus::DaCongBo;
        // CR-71/144: đáp án mở khi mọi kỳ dùng đề đã đóng hoặc công bố; điểm chỉ hiện khi kỳ đã công bố.
        $reveal = $quiz->answers_open_at !== null;
        $summary = AttemptDetails::summary($attempt);

        return Inertia::render('exams/result', [
            'event' => ['id' => $event->id, 'title' => $event->title, 'status_label' => $event->status->label(), 'url' => route('exams.show', $event)],
            'attempt' => [
                'status_label' => $summary['status_label'], 'close_reason' => $summary['close_reason'],
                'started_at' => $summary['started_at'], 'closed_at' => $summary['closed_at'],
                'percent' => $published ? $summary['percent'] : null,
                'correct_count' => $published ? $summary['correct_count'] : null,
                'question_count' => $summary['question_count'],
                'rank' => $published ? ExamRanking::query()->where('quiz_attempt_id', $attempt->id)->value('rank') : null,
                'unranked' => ExamTaking::unranked($user, $event),
            ],
            'published' => $published,
            'answers_visible' => $reveal,
            'questions' => $reveal ? AttemptDetails::questions($attempt, true, fn () => '') : [],
            'ranking' => $published ? ExamBoard::top($event, $user) : [],
        ]);
    }

    /** Lượt quá hạn được chốt ngay khi chủ lượt mở lại (PX theo hạn lượt, không phụ thuộc tác vụ nền). */
    private function settle(User $user, QuizAttempt $attempt): QuizAttempt
    {
        abort_unless($attempt->exam_event_id !== null && $attempt->user_id === $user->id, 403);

        return Tx::run(function () use ($attempt) {
            $event = ExamEvent::query()->whereKey($attempt->exam_event_id)->lockForUpdate()->firstOrFail();
            $attempt = QuizAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            return app(ExamTaking::class)->settleUnderLock($event, $attempt, now()->floorMillisecond());
        });
    }

    private function visible(User $user, ExamEvent $exam): void
    {
        abort_if($exam->status === ExamStatus::Nhap && $exam->created_by_id !== $user->id, 404);
        abort_unless($exam->audience === ExamAudience::CongKhai || $exam->created_by_id === $user->id
            || ExamTaking::hasDesignatedEnrollment($user, $exam, false)
            || QuizAttempt::query()->where('exam_event_id', $exam->id)->where('user_id', $user->id)->exists(), 403);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
