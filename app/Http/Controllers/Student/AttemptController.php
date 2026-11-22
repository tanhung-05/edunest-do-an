<?php

namespace App\Http\Controllers\Student;

use App\Actions\M10\ContinueQuizAttempt;
use App\Actions\M10\SaveAttemptAnswer;
use App\Actions\M10\SubmitQuizAttempt;
use App\Enums\M09\Activity;
use App\Enums\M10\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\M04\QuizQuestion;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Policies\M10\QuizPolicy;
use App\Queries\M10\AttemptDetails;
use App\Queries\M10\QuizAttempts;
use App\Support\DomainRejection;
use App\Support\M09\LearningAccess;
use App\Support\M10\QuizScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AttemptController extends Controller
{
    public function show(Request $request, QuizAttempt $attempt, ContinueQuizAttempt $continue): Response|RedirectResponse
    {
        $attempt = $continue->handle($this->student($request), $attempt);
        if ($attempt->status !== AttemptStatus::DangLam) {
            return to_route('student.attempts.result', $attempt);
        }

        return Inertia::render('student/attempts/show', [
            'attempt' => AttemptDetails::summary($attempt),
            'questions' => AttemptDetails::questions($attempt, false),
            'submit_url' => route('student.attempts.submit', $attempt),
            'server_time' => now()->toISOString(),
        ]);
    }

    public function save(Request $request, QuizAttempt $attempt, QuizQuestion $quizQuestion, SaveAttemptAnswer $save): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['answer' => ['present', 'nullable']]);
        try {
            $saved = $save->handle($this->student($request), $attempt, $quizQuestion, $data['answer']);
        } catch (DomainRejection $failure) {
            return $this->redirectAfterClose($request, $attempt, $failure);
        }

        return $request->expectsJson() ? response()->json(['received_at' => $saved->received_at->toISOString()]) : back();
    }

    public function submit(Request $request, QuizAttempt $attempt, SubmitQuizAttempt $submit): RedirectResponse
    {
        try {
            $attempt = $submit->handle($this->student($request), $attempt);
        } catch (DomainRejection $failure) {
            return $this->redirectAfterClose($request, $attempt, $failure);
        }

        return to_route('student.attempts.result', $attempt);
    }

    public function result(Request $request, QuizAttempt $attempt, ContinueQuizAttempt $continue, QuizPolicy $policy): Response|RedirectResponse
    {
        $user = $this->student($request);
        $attempt = $continue->handle($user, $attempt);
        if ($attempt->status === AttemptStatus::DangLam) {
            return to_route('student.attempts.show', $attempt);
        }
        $quiz = $attempt->quiz()->firstOrFail();
        $enrollment = $attempt->enrollment()->firstOrFail();
        $reveal = $policy->viewAnswers($user, $quiz, $attempt);
        $parameters = ['course' => $enrollment->course_id, 'quiz' => $quiz->id];
        $attempts = QuizAttempts::for($enrollment, $quiz);
        $lesson = $quiz->lesson;
        $next = $lesson === null ? null : LearningAccess::lessons($enrollment)
            ->skipUntil(fn ($item) => $item->id === $lesson->id)->skip(1)->first();

        return Inertia::render('student/attempts/result', [
            'attempt' => AttemptDetails::summary($attempt),
            'best_percent' => QuizScore::display(QuizAttempts::best($enrollment, $quiz)),
            'questions' => $reveal ? AttemptDetails::questions($attempt, true) : [],
            'answers_visible' => $reveal,
            'quiz_url' => route('student.quizzes.show', $parameters),
            'history_url' => route('student.quizzes.history', $parameters),
            'can_start' => $policy->startAttempt($user, $quiz, $enrollment)
                && (clone $attempts)->count() < QuizAttempts::limit($enrollment, $quiz)
                && ! $attempts->where(fn ($q) => $q->where('passed', true)->orWhere('status', AttemptStatus::DangLam))->exists(),
            'next_lesson_url' => $next !== null && LearningAccess::allows($user, $enrollment, Activity::ViewLesson, $next)
                ? route('student.lessons.show', ['course' => $enrollment->course_id, 'lesson' => $next->id]) : null,
        ]);
    }

    private function student(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function redirectAfterClose(Request $request, QuizAttempt $attempt, DomainRejection $failure): RedirectResponse
    {
        if ($request->expectsJson() || $failure->status !== 422 || $attempt->refresh()->status === AttemptStatus::DangLam) {
            throw $failure;
        }

        return to_route('student.attempts.result', $attempt)->with('status', $failure->getMessage());
    }
}
