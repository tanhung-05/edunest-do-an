<?php

namespace App\Http\Controllers\Student;

use App\Actions\M26\LiveSessions;
use App\Enums\M08\EnrollmentValidity;
use App\Http\Controllers\Controller;
use App\Models\M08\Enrollment;
use App\Models\M26\LiveSession;
use App\Models\M26\LiveSessionJoin;
use App\Models\M26\LiveSessionRecording;
use App\Models\User;
use App\Support\M08\EnrollmentValidityChecker;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** MH-HV buổi học trực tiếp (QT-28 bước 3–7, A6): lịch của các khóa còn hạn, vào phòng, xem bản ghi hiện hành. */
class LiveSessionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->student($request);
        $now = CarbonImmutable::now();
        $enrollments = Enrollment::query()->with('course:id,title')->where('user_id', $user->id)->get()
            ->filter(fn (Enrollment $e) => EnrollmentValidityChecker::for($e, $now) === EnrollmentValidity::ConHan)->keyBy('course_id');
        $sessions = LiveSession::query()->with('currentRecording')->whereIn('course_id', $enrollments->keys())
            ->where('starts_at', '>=', $now->subDays(60))->orderBy('starts_at')->get();
        $joined = LiveSessionJoin::query()->where('user_id', $user->id)
            ->whereIn('live_session_id', $sessions->pluck('id'))->pluck('first_clicked_at', 'live_session_id');

        return Inertia::render('student/live', [
            'now' => $now->toIso8601String(),
            'sessions' => $sessions->map(fn (LiveSession $s) => [
                'id' => $s->id,
                'course' => $enrollments->get($s->course_id)?->course?->title,
                'title' => $s->title,
                'description' => $s->description,
                'starts_at' => $s->starts_at->toIso8601String(),
                'ends_at' => $s->endsAt()->toIso8601String(),
                'duration_minutes' => $s->duration_minutes,
                'state' => $s->cancelled_at !== null ? 'DA_HUY' : ($now->gt($s->endsAt()) ? 'DA_KET_THUC'
                    : ($s->joinable($now) ? 'VAO_DUOC' : 'SAP_TOI')),
                'joined_at' => isset($joined[$s->id]) ? CarbonImmutable::parse($joined[$s->id])->toIso8601String() : null,
                'join_url' => route('student.live.join', $s),
                'recording_url' => $s->cancelled_at === null && $s->currentRecording !== null
                    ? route('student.live.recording', $s->currentRecording) : null,
            ])->values(),
        ]);
    }

    /** Bước 5–7: ghi lần bấm đầu và chuyển tới link phòng họp hiện hành. */
    public function join(Request $request, LiveSession $session, LiveSessions $live): HttpResponse
    {
        $user = $this->student($request);
        $enrollment = Enrollment::query()->where('user_id', $user->id)->where('course_id', $session->course_id)->first();
        abort_if($enrollment === null, 403, 'Bạn không học khóa của buổi này.');
        $url = $live->join($user, $session, $enrollment, CarbonImmutable::now());

        return Inertia::location($url);
    }

    /** A6: mỗi lần mở kiểm tệp hiện hành và ghi danh còn hạn; tệp đã bị thay bị từ chối. */
    public function recording(Request $request, LiveSessionRecording $recording, LiveSessions $live): BinaryFileResponse
    {
        $user = $this->student($request);
        $courseId = LiveSession::query()->whereKey($recording->live_session_id)->value('course_id');
        $enrollment = Enrollment::query()->where('user_id', $user->id)->where('course_id', $courseId)->first();
        abort_if($enrollment === null, 403, 'Bạn không học khóa của buổi này.');
        $rec = $live->recording($user, $recording, $enrollment, CarbonImmutable::now());
        $disk = Storage::disk('local');
        abort_unless($disk->exists($rec->file_path), 404);
        $response = response()->file($disk->path($rec->file_path), ['Content-Type' => $rec->mime_type, 'Cache-Control' => 'private, no-store']);
        $response->setContentDisposition('inline', $rec->original_name, 'ban-ghi');

        return $response;
    }

    private function student(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
