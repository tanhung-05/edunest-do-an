<?php

namespace App\Http\Controllers\Student;

use App\Actions\M10\ContinueQuizAttempt;
use App\Actions\M10\StartQuizAttempt;
use App\Enums\M10\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M04\Quiz;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Policies\M10\QuizAttemptPolicy;
use App\Policies\M10\QuizPolicy;
use App\Queries\M09\LearningEntry;
use App\Queries\M10\AttemptDetails;
use App\Queries\M10\QuizAttempts;
use App\Support\M09\LearningAccess;
use App\Support\M10\QuizScore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class QuizController extends Controller
{
    public function show(Request $request, Course $course, Quiz $quiz, QuizPolicy $policy, ContinueQuizAttempt $continue): Response
    {
        [$user, $enrollment] = $this->context($request, $course, $quiz);
        $attempts = QuizAttempts::for($enrollment, $quiz);
        $open = (clone $attempts)->where('status', AttemptStatus::DangLam)->first();
        if ($open !== null) {
            $open = $continue->handle($user, $open);
        }
        $allowed = $policy->startAttempt($user, $quiz, $enrollment);
        $history = (clone $attempts)->where('status', '!=', AttemptStatus::DangLam);
        abort_if(! $allowed && $open === null && ! $history->exists(), 403, 'Bài quiz chưa mở hoặc quyền học đã hết hiệu lực.');
        $used = $attempts->count();
        $limit = QuizAttempts::limit($enrollment, $quiz);
        $running = $open !== null && $open->status === AttemptStatus::DangLam;

        return Inertia::render('student/quizzes/show', [
            'title' => $quiz->lesson?->title,
            'duration_minutes' => $quiz->duration_minutes,
            'pass_percent' => $quiz->pass_percent,
            'used' => $used,
            'limit' => $limit,
            'best_percent' => QuizScore::display(QuizAttempts::best($enrollment, $quiz)),
            'continue_url' => $running ? route('student.attempts.show', $open) : null,
            'start_url' => $allowed && ! $running && $used < $limit && ! $attempts->where('passed', true)->exists()
                ? route('student.quizzes.attempts.store', ['course' => $course->id, 'quiz' => $quiz->id]) : null,
            'history_url' => route('student.quizzes.history', ['course' => $course->id, 'quiz' => $quiz->id]),
        ]);
    }

    public function start(Request $request, Course $course, Quiz $quiz, StartQuizAttempt $start): RedirectResponse
    {
        [$user, $enrollment] = $this->context($request, $course, $quiz);
        $attempt = $start->handle($user, $enrollment, $quiz);

        return to_route($attempt->status === AttemptStatus::DangLam ? 'student.attempts.show' : 'student.attempts.result', $attempt);
    }

    public function history(Request $request, Course $course, Quiz $quiz): Response
    {
        [, $enrollment] = $this->context($request, $course, $quiz, false);

        return Inertia::render('student/quizzes/history', [
            'title' => $quiz->lesson?->title,
            'attempts' => QuizAttempts::for($enrollment, $quiz)->where('status', '!=', AttemptStatus::DangLam)->orderByDesc('attempt_no')->get()->map(AttemptDetails::summary(...)),
            'best_percent' => QuizScore::display(QuizAttempts::best($enrollment, $quiz)),
        ]);
    }

    /** @return array{User, Enrollment} */
    private function context(Request $request, Course $course, Quiz $quiz, bool $currentVersion = true): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $enrollment = LearningEntry::enrollment($user, $course);
        $lesson = $quiz->lesson;
        if ($currentVersion) {
            abort_unless($lesson !== null && $quiz->course_version_id === $enrollment->course_version_id && LearningAccess::matches($enrollment, $lesson), 403);
        } else {
            abort_unless(app(QuizAttemptPolicy::class)->viewAny($user, $quiz, $enrollment), 403);
        }

        return [$user, $enrollment];
    }
}
