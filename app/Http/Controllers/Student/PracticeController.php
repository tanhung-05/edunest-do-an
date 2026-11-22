<?php

namespace App\Http\Controllers\Student;

use App\Actions\M20\PracticeSessions;
use App\Enums\M20\PracticeMode;
use App\Enums\M20\QuestionResult;
use App\Enums\M20\SessionStatus;
use App\Enums\M21\XpSourceType;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M08\Enrollment;
use App\Models\M20\PracticeActivity;
use App\Models\M20\PracticeQuestion;
use App\Models\M20\PracticeSession;
use App\Models\User;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M10\QuizScore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** MH-HV-16/17: luyện tập Learning/Try Hard (QT-22). */
final class PracticeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $enrollments = Enrollment::query()->where('user_id', $user->id)->whereNull('revoked_at')->get()->keyBy('course_id');
        $activities = PracticeActivity::query()->whereIn('course_id', $enrollments->keys())->whereNull('archived_at')->orderBy('id')->get()->groupBy('course_id');
        $courses = Course::query()->whereIn('id', $activities->keys())->orderBy('title')->get(['id', 'title']);
        $open = PracticeSession::query()->where('user_id', $user->id)->where('status', SessionStatus::DangLam)->pluck('id', 'activity_id');

        return Inertia::render('student/practice/index', [
            'courses' => $courses->map(fn (Course $c) => [
                'id' => $c->id, 'title' => $c->title,
                'active' => ($e = $enrollments->get($c->id)) !== null && EnrollmentValidityChecker::for($e)->value === 'CON_HAN',
                'activities' => $activities->get($c->id, collect())->map(fn (PracticeActivity $a) => [
                    'id' => $a->id, 'title' => $a->title, 'question_count' => $a->question_count, 'seconds_per_question' => $a->seconds_per_question,
                    'draw_mode' => $a->draw_mode->label(), 'open_session_url' => isset($open[$a->id]) ? route('student.practice.sessions.show', $open[$a->id]) : null,
                    'start_url' => route('student.practice.start', $a),
                ])->values(),
            ])->values(),
            'history' => self::history($user),
        ]);
    }

    public function start(Request $request, PracticeActivity $activity, PracticeSessions $sessions): RedirectResponse
    {
        $data = $request->validate(['mode' => ['required', Rule::enum(PracticeMode::class)]]);
        $session = $sessions->start($this->user($request), $activity, PracticeMode::from($data['mode']));

        return to_route('student.practice.sessions.show', $session);
    }

    public function show(Request $request, PracticeSession $session, PracticeSessions $sessions): Response|RedirectResponse
    {
        $user = $this->user($request);
        $this->owns($user, $session);
        $session = $sessions->refresh($user, $session);
        if ($session->status !== SessionStatus::DangLam) {
            return to_route('student.practice.sessions.result', $session);
        }
        $questions = PracticeQuestion::query()->where('activity_session_id', $session->id)->orderBy('position')->get();
        $current = $questions->firstWhere('position', $session->current_position);
        $last = $questions->whereNotNull('result')->sortByDesc('position')->first();
        $learning = $session->mode === PracticeMode::Learning;

        return Inertia::render('student/practice/session', [
            'session' => [
                'id' => $session->id, 'title' => (string) PracticeActivity::query()->whereKey($session->activity_id)->value('title'),
                'mode' => $session->mode->value, 'mode_label' => $session->mode->label(), 'lives' => $session->lives,
                'position' => $session->current_position, 'count' => $session->question_count,
                'deadline_ms' => $session->question_deadline_at === null ? null : max(0, (int) now()->diffInMilliseconds($session->question_deadline_at, false)),
                'seconds_per_question' => $session->seconds_per_question,
                'answer_url' => route('student.practice.sessions.answer', $session), 'hint_url' => route('student.practice.sessions.hint', $session),
                'results' => $questions->map(fn (PracticeQuestion $q) => $q->result?->value)->values(),
            ],
            'question' => $current === null ? null : [
                'position' => $current->position, 'stem' => $current->snapshot['stem'] ?? '', 'type' => $current->snapshot['type'] ?? null,
                'difficulty' => $current->snapshot['difficulty'] ?? null, 'image_url' => self::image($current->snapshot['image_path'] ?? null),
                'choices' => collect(QuizScore::options(PracticeSessions::grader($current)))->map(fn ($o) => ['id' => $o['id'], 'content' => $o['content']])->values(),
                'tries' => $current->tries, 'hint' => $current->hint_used ? ($current->snapshot['hint'] ?? null) : null,
                'has_hint' => $learning && ! $current->hint_used && trim((string) ($current->snapshot['hint'] ?? '')) !== '',
                'retry' => $learning && $current->tries === 1,
            ],
            // BR-XP-10: Learning hiện đáp án và lời giải khi câu đã xong; Try Hard chỉ báo đúng/sai tới khi chốt phiên.
            'feedback' => $last === null ? null : [
                'position' => $last->position, 'result' => $last->result?->value, 'result_label' => $last->result?->label(),
                'correct' => $last->result === QuestionResult::Dung,
                'answer' => $learning ? self::reveal($last) : null,
            ],
        ]);
    }

    public function answer(Request $request, PracticeSession $session, PracticeSessions $sessions): RedirectResponse
    {
        $data = $request->validate([
            'position' => ['required', 'integer', 'min:1'], 'answer' => ['nullable'], 'answer.*' => ['integer'],
            'request_id' => ['required', 'string', 'max:40'],
        ]);
        $user = $this->user($request);
        $this->owns($user, $session);
        $session = $sessions->answer($user, $session, (int) $data['position'], $data['answer'] ?? null, $data['request_id']);

        return $session->status === SessionStatus::DangLam ? back() : to_route('student.practice.sessions.result', $session);
    }

    public function hint(Request $request, PracticeSession $session, PracticeSessions $sessions): RedirectResponse
    {
        $user = $this->user($request);
        $this->owns($user, $session);
        $sessions->hint($user, $session);

        return back();
    }

    public function result(Request $request, PracticeSession $session): Response|RedirectResponse
    {
        $user = $this->user($request);
        $this->owns($user, $session);
        if ($session->status === SessionStatus::DangLam) {
            return to_route('student.practice.sessions.show', $session);
        }
        $questions = PracticeQuestion::query()->where('activity_session_id', $session->id)->orderBy('position')->get();
        $xp = self::xp($session->id);

        return Inertia::render('student/practice/result', [
            'session' => [
                'id' => $session->id, 'title' => (string) PracticeActivity::query()->whereKey($session->activity_id)->value('title'),
                'mode_label' => $session->mode->label(), 'status' => $session->status->value, 'status_label' => $session->status->label(),
                'end_reason' => $session->end_reason?->label(), 'count' => $session->question_count,
                'correct' => $questions->where('result', QuestionResult::Dung)->count(), 'lives' => $session->lives,
                'started_at' => $session->started_at->toIso8601String(), 'ended_at' => $session->ended_at?->toIso8601String(),
                'factor' => (float) $session->level_factor, 'xp' => $xp, 'xp_pending' => $xp === null && $session->status !== SessionStatus::BoDo,
                'retry_url' => route('student.practice.start', $session->activity_id), 'mode' => $session->mode->value,
            ],
            'questions' => $questions->map(fn (PracticeQuestion $q) => [
                'position' => $q->position, 'stem' => $q->snapshot['stem'] ?? '', 'result' => $q->result?->value, 'result_label' => $q->result?->label() ?? 'Chưa làm',
                'tries' => $q->tries, 'hint_used' => $q->hint_used, 'given' => self::given($q), 'answer' => self::reveal($q),
            ])->values(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public static function history(User $user, int $limit = 15): array
    {
        $sessions = PracticeSession::query()->where('user_id', $user->id)->where('status', '!=', SessionStatus::DangLam)
            ->latest('ended_at')->latest('id')->limit($limit)->get();
        $titles = PracticeActivity::query()->whereIn('id', $sessions->pluck('activity_id'))->pluck('title', 'id');
        $xp = DB::table('xp_transactions')->join('xp_sources', 'xp_sources.id', '=', 'xp_transactions.xp_source_id')
            ->where('xp_sources.type', XpSourceType::PhienLuyenTap->value)->whereIn('xp_sources.source_key', $sessions->pluck('id')->map(fn ($id) => (string) $id))
            ->groupBy('xp_sources.source_key')->selectRaw('xp_sources.source_key, SUM(xp_transactions.amount) AS xp')->pluck('xp', 'source_key');

        return array_values($sessions->map(fn (PracticeSession $s) => [
            'id' => $s->id, 'title' => $titles[$s->activity_id] ?? '', 'mode' => $s->mode->label(), 'status' => $s->status->value, 'status_label' => $s->status->label(),
            'ended_at' => $s->ended_at?->toIso8601String(), 'xp' => (int) ($xp[(string) $s->id] ?? 0), 'url' => route('student.practice.sessions.result', $s),
        ])->values()->all());
    }

    private static function xp(int $sessionId): ?int
    {
        $source = DB::table('xp_sources')->where('type', XpSourceType::PhienLuyenTap->value)->where('source_key', (string) $sessionId)->first(['id', 'processed_at']);
        if ($source === null || $source->processed_at === null) {
            return null;
        }

        return (int) DB::table('xp_transactions')->where('xp_source_id', $source->id)->sum('amount');
    }

    /** @return array{choices: list<array{content: string, is_correct: bool}>, ordered: bool, explanation: ?string} */
    private static function reveal(PracticeQuestion $q): array
    {
        $options = QuizScore::options(PracticeSessions::grader($q));
        $ordered = ($q->snapshot['type'] ?? null) === 'SAP_XEP';
        if ($ordered) {
            usort($options, fn ($a, $b) => $a['position'] <=> $b['position']);
        }

        return ['choices' => array_map(fn ($o) => ['content' => $o['content'], 'is_correct' => $o['is_correct']], $options), 'ordered' => $ordered,
            'explanation' => $q->snapshot['explanation'] ?? null];
    }

    private static function given(PracticeQuestion $q): ?string
    {
        $answer = $q->answer;
        if ($answer === null || $answer === []) {
            return null;
        }
        if (is_string($answer)) {
            return $answer;
        }
        $byId = collect(QuizScore::options(PracticeSessions::grader($q)))->keyBy('id');

        return collect((array) $answer)->map(fn ($id) => $byId->get($id)['content'] ?? '?')->implode(($q->snapshot['type'] ?? '') === 'SAP_XEP' ? ' → ' : ', ');
    }

    private static function image(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }

    private function owns(User $user, PracticeSession $session): void
    {
        abort_unless($session->user_id === $user->id, 403);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
