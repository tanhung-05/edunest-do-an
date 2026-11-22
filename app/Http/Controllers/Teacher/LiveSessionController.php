<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\M26\LiveSessions;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M03\CourseAuthor;
use App\Models\M26\LiveSession;
use App\Models\M26\LiveSessionJoin;
use App\Models\User;
use App\Support\M03\AuthoringScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-GV buổi trực tiếp (QT-28 bước 1–2, A1, A2, A4, A5): chỉ khóa đang được giao. */
class LiveSessionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->teacher($request);
        $courseIds = CourseAuthor::query()->where('user_id', $user->id)->whereNotNull('current_key')->pluck('course_id');
        $courses = Course::query()->whereIn('id', $courseIds)->orderBy('title')->get(['id', 'title']);
        $now = CarbonImmutable::now();
        $sessions = LiveSession::query()->with('currentRecording')->whereIn('course_id', $courseIds)
            ->orderByDesc('starts_at')->limit(100)->get();
        $joins = LiveSessionJoin::query()->join('users', 'users.id', '=', 'live_session_joins.user_id')
            ->whereIn('live_session_id', $sessions->pluck('id'))->orderBy('first_clicked_at')
            ->get(['live_session_id', 'users.name', 'first_clicked_at'])->groupBy('live_session_id');

        return Inertia::render('teacher/live/index', [
            'courses' => $courses->map(fn (Course $c) => ['id' => $c->id, 'name' => $c->title])->values(),
            'now' => $now->toIso8601String(),
            'sessions' => $sessions->map(fn (LiveSession $s) => [
                'id' => $s->id,
                'course_id' => $s->course_id,
                'course' => $courses->firstWhere('id', $s->course_id)?->title,
                'title' => $s->title,
                'description' => $s->description,
                'starts_at' => $s->starts_at->toIso8601String(),
                'duration_minutes' => $s->duration_minutes,
                'meeting_url' => $s->meeting_url,
                'cancelled' => $s->cancelled_at !== null,
                'ended' => $now->gt($s->endsAt()),
                'can_reschedule' => $s->cancelled_at === null && $now->lt($s->starts_at->subMinutes(LiveSessions::JOIN_BEFORE_MINUTES)),
                'recording' => $s->currentRecording?->original_name,
                'joins' => ($joins->get($s->id) ?? collect())->map(fn ($j) => [
                    'name' => $j->getAttribute('name'), 'at' => CarbonImmutable::parse($j->getAttribute('first_clicked_at'))->toIso8601String(),
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request, LiveSessions $live): RedirectResponse
    {
        $data = $this->validated($request, true);
        $course = Course::query()->whereKey($data['course_id'])->firstOrFail();
        abort_unless(AuthoringScope::isCurrentAuthor($this->teacher($request), $course), 403);
        $live->schedule($this->teacher($request), $course, $this->payload($data), CarbonImmutable::now());

        return back()->with('status', 'Đã lên lịch buổi trực tiếp.');
    }

    public function update(Request $request, LiveSession $session, LiveSessions $live): RedirectResponse
    {
        $data = $this->validated($request, false);
        $live->update($this->teacher($request), $session, $this->payload($data), CarbonImmutable::now());

        return back()->with('status', 'Đã lưu buổi trực tiếp.');
    }

    public function cancel(Request $request, LiveSession $session, LiveSessions $live): RedirectResponse
    {
        $live->cancel($this->teacher($request), $session, CarbonImmutable::now());

        return back()->with('status', 'Đã hủy buổi. Hãy tự đóng phòng trên dịch vụ họp nếu đã mở.');
    }

    public function recording(Request $request, LiveSession $session, LiveSessions $live): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'max:512000', 'mimes:mp4,webm,mp3,m4a,pdf']]);
        $live->uploadRecording($this->teacher($request), $session, $data['file'], CarbonImmutable::now());

        return back()->with('status', 'Đã tải bản ghi.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'course_id' => [$creating ? 'required' : 'prohibited', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'meeting_url' => ['required', 'string', 'max:500'],
        ]);
    }

    /**
     * Giờ nhập theo giờ Việt Nam từ ô datetime-local.
     *
     * @param  array<string, mixed>  $data
     * @return array{title: string, description: ?string, starts_at: CarbonImmutable, duration_minutes: int, meeting_url: string}
     */
    private function payload(array $data): array
    {
        return [
            'title' => (string) $data['title'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'starts_at' => CarbonImmutable::parse((string) $data['starts_at'], 'Asia/Ho_Chi_Minh')->utc(),
            'duration_minutes' => (int) $data['duration_minutes'],
            'meeting_url' => trim((string) $data['meeting_url']),
        ];
    }

    private function teacher(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
