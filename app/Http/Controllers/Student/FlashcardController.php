<?php

namespace App\Http\Controllers\Student;

use App\Actions\M21\XpLedger;
use App\Actions\M22\FlashcardReviews;
use App\Actions\M22\GameSessions;
use App\Enums\M20\SessionStatus;
use App\Enums\M21\XpSourceType;
use App\Enums\M22\CardResult;
use App\Enums\M22\GameKind;
use App\Enums\M22\ReviewGrade;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M08\Enrollment;
use App\Models\M22\Flashcard;
use App\Models\M22\FlashcardSet;
use App\Models\M22\GameCard;
use App\Models\M22\GameSession;
use App\Models\User;
use App\Support\M08\EnrollmentValidityChecker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** MH-HV-19/20: thẻ ghi nhớ, hàng "Thẻ cần ôn hôm nay", Bắn bong bóng, Mưa chữ (QT-24). */
final class FlashcardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $enrollments = Enrollment::query()->where('user_id', $user->id)->whereNull('revoked_at')->get()->keyBy('course_id');
        $sets = FlashcardSet::query()->whereIn('course_id', $enrollments->keys())->whereNull('archived_at')->orderBy('course_id')->orderBy('title')->get();
        $courses = Course::query()->whereIn('id', $sets->pluck('course_id'))->pluck('title', 'id');
        $today = self::today();
        $due = [];
        foreach ($sets as $set) {
            $due[$set->id] = self::queue($user, $set, $today)->count();
        }

        return Inertia::render('student/flashcards/index', [
            'sets' => $sets->map(fn (FlashcardSet $s) => [
                'id' => $s->id, 'title' => $s->title, 'description' => $s->description, 'course' => $courses[$s->course_id] ?? '',
                'cards' => Flashcard::query()->where('flashcard_set_id', $s->id)->whereNull('archived_at')->count(), 'due' => $due[$s->id],
                'active' => ($e = $enrollments->get($s->course_id)) !== null && EnrollmentValidityChecker::for($e)->value === 'CON_HAN',
                'review_url' => route('student.flashcards.review', $s), 'game_url' => route('student.games.start', $s),
            ])->values(),
            'due_total' => array_sum($due),
            'suggested' => self::suggested($user, $today),
            'games' => GameSession::query()->where('user_id', $user->id)->where('status', '!=', SessionStatus::DangLam)->latest('ended_at')->limit(8)->get()
                ->map(fn (GameSession $g) => ['id' => $g->id, 'kind' => $g->kind->label(), 'status' => $g->status->label(), 'ended_at' => $g->ended_at?->toIso8601String(),
                    'url' => route('student.games.result', $g)])->values(),
        ]);
    }

    public function review(Request $request, FlashcardSet $set): Response
    {
        $user = $this->user($request);
        $enrollment = Enrollment::query()->where('user_id', $user->id)->where('course_id', $set->course_id)->whereNull('revoked_at')->first();
        abort_if($enrollment === null || $set->archived_at !== null, 403);
        $queue = self::queue($user, $set, self::today());

        return Inertia::render('student/flashcards/review', [
            'set' => ['id' => $set->id, 'title' => $set->title, 'active' => EnrollmentValidityChecker::for($enrollment)->value === 'CON_HAN'],
            'cards' => $queue->map(fn (Flashcard $c) => self::card($c) + ['grade_url' => route('student.flashcards.grade', $c)])->values(),
            'grades' => collect(ReviewGrade::cases())->map(fn (ReviewGrade $g) => ['value' => $g->value, 'label' => $g->label()])->values(),
        ]);
    }

    public function grade(Request $request, Flashcard $card, FlashcardReviews $reviews): RedirectResponse
    {
        $data = $request->validate(['grade' => ['required', Rule::enum(ReviewGrade::class)], 'back_version' => ['required', 'integer'],
            'request_id' => ['required', 'string', 'max:40']]);
        $review = $reviews->grade($this->user($request), $card, ReviewGrade::from($data['grade']), (int) $data['back_version'], $data['request_id']);

        return back()->with('status', 'Ôn lại vào '.$review->due_on?->format('d/m/Y').'.');
    }

    public function startGame(Request $request, FlashcardSet $set, GameSessions $games): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', Rule::enum(GameKind::class)]]);

        return to_route('student.games.play', $games->start($this->user($request), $set, GameKind::from($data['kind'])));
    }

    public function play(Request $request, GameSession $session, GameSessions $games): Response|RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($session->user_id === $user->id, 403);
        $session = $games->refresh($user, $session);
        if ($session->status !== SessionStatus::DangLam) {
            return to_route('student.games.result', $session);
        }
        $cards = GameCard::query()->where('game_session_id', $session->id)->orderBy('position')->get();
        $current = $cards->firstWhere('position', $session->current_position);
        $last = $cards->whereNotNull('result')->sortByDesc('position')->first();

        return Inertia::render('student/games/play', [
            'game' => ['id' => $session->id, 'kind' => $session->kind->value, 'kind_label' => $session->kind->label(),
                'set' => (string) FlashcardSet::query()->whereKey($session->flashcard_set_id)->value('title'),
                'position' => $session->current_position, 'count' => $session->card_count, 'answer_url' => route('student.games.answer', $session),
                'results' => $cards->map(fn (GameCard $c) => $c->result?->value)->values()],
            'card' => $current === null ? null : ['position' => $current->position, 'front' => $current->snapshot['front'] ?? '',
                'image_url' => self::image($current->snapshot['image_path'] ?? null), 'speech_lang' => $current->snapshot['speech_lang'] ?? null,
                'options' => $current->snapshot['options'] ?? []],
            'last' => $last === null ? null : ['position' => $last->position, 'result' => $last->result?->value, 'back' => $last->snapshot['back'] ?? '',
                'front' => $last->snapshot['front'] ?? ''],
        ]);
    }

    public function answerGame(Request $request, GameSession $session, GameSessions $games): RedirectResponse
    {
        $data = $request->validate(['position' => ['required', 'integer', 'min:1'], 'value' => ['nullable', 'string', 'max:500'], 'bottom' => ['boolean'],
            'request_id' => ['required', 'string', 'max:40']]);
        $user = $this->user($request);
        abort_unless($session->user_id === $user->id, 403);
        $session = $games->answer($user, $session, (int) $data['position'], $data['value'] ?? null, (bool) ($data['bottom'] ?? false), $data['request_id']);

        return $session->status === SessionStatus::DangLam ? back() : to_route('student.games.result', $session);
    }

    public function gameResult(Request $request, GameSession $session): Response|RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($session->user_id === $user->id, 403);
        if ($session->status === SessionStatus::DangLam) {
            return to_route('student.games.play', $session);
        }
        $cards = GameCard::query()->where('game_session_id', $session->id)->orderBy('position')->get();
        $source = DB::table('xp_sources')->where('type', XpSourceType::PhienGame->value)->where('source_key', (string) $session->id)->first(['id', 'processed_at']);
        $map = fn (GameCard $c) => ['front' => $c->snapshot['front'] ?? '', 'back' => $c->snapshot['back'] ?? '', 'result' => $c->result?->label() ?? 'Chưa chơi'];

        return Inertia::render('student/games/result', [
            'game' => ['id' => $session->id, 'kind_label' => $session->kind->label(), 'status' => $session->status->value, 'status_label' => $session->status->label(),
                'end_reason' => $session->end_reason?->label(), 'count' => $session->card_count, 'factor' => (float) $session->level_factor,
                'set' => (string) FlashcardSet::query()->whereKey($session->flashcard_set_id)->value('title'),
                'xp' => $source?->processed_at === null ? null : (int) DB::table('xp_transactions')->where('xp_source_id', $source->id)->sum('amount'),
                'replay_url' => route('student.games.start', $session->flashcard_set_id), 'kind' => $session->kind->value,
                'review_url' => route('student.flashcards.review', $session->flashcard_set_id)],
            'good' => $cards->where('result', CardResult::Dung)->map($map)->values(),
            'again' => $cards->filter(fn (GameCard $c) => $c->result !== null && $c->result !== CardResult::Dung)->map($map)->values(),
        ]);
    }

    /**
     * BR-XP-62: thẻ đến hạn (≤ hôm nay VN), thẻ mới và thẻ đặt lại (CR-90).
     *
     * @return Collection<int, Flashcard>
     */
    public static function queue(User $user, FlashcardSet $set, string $today): Collection
    {
        $reviews = DB::table('flashcard_reviews')->where('user_id', $user->id)
            ->whereIn('flashcard_id', Flashcard::query()->where('flashcard_set_id', $set->id)->select('id'))->get()->keyBy('flashcard_id');

        return Flashcard::query()->where('flashcard_set_id', $set->id)->whereNull('archived_at')->orderBy('position')->orderBy('id')->get()
            ->filter(function (Flashcard $c) use ($reviews, $today) {
                $r = $reviews->get($c->id);

                return $r === null || $r->due_on === null || (int) $r->back_version !== $c->back_version || substr((string) $r->due_on, 0, 10) <= $today;
            })->values();
    }

    /**
     * BR-XP-66: danh sách tạm thẻ trả lời sai trong game hôm nay; không đổi lịch ôn.
     *
     * @return list<array<string, mixed>>
     */
    private static function suggested(User $user, string $today): array
    {
        $from = CarbonImmutable::parse($today, XpLedger::TZ)->utc()->format('Y-m-d H:i:s');

        return array_values(GameCard::query()->join('game_sessions', 'game_sessions.id', '=', 'game_session_cards.game_session_id')
            ->where('game_sessions.user_id', $user->id)->where('game_session_cards.answered_at', '>=', $from)
            ->whereIn('game_session_cards.result', [CardResult::Sai->value, CardResult::ChamDay->value])
            ->get(['game_session_cards.flashcard_id', 'game_session_cards.snapshot', 'game_sessions.flashcard_set_id'])->unique('flashcard_id')->take(30)
            ->map(fn (GameCard $c) => ['id' => $c->flashcard_id, 'front' => $c->snapshot['front'] ?? '', 'back' => $c->snapshot['back'] ?? '',
                'review_url' => route('student.flashcards.review', (int) $c->getAttribute('flashcard_set_id'))])->all());
    }

    /** @return array<string, mixed> */
    private static function card(Flashcard $c): array
    {
        return ['id' => $c->id, 'front' => $c->front, 'back' => $c->back, 'back_version' => $c->back_version, 'image_url' => self::image($c->image_path),
            'speech_lang' => $c->speech_lang];
    }

    private static function today(): string
    {
        return CarbonImmutable::now(XpLedger::TZ)->toDateString();
    }

    private static function image(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
