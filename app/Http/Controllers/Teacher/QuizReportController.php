<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\M10\AttemptStatus;
use App\Models\M03\Course;
use App\Models\M04\Quiz;
use App\Models\M05\CourseVersion;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Queries\M10\QuizAttempts;
use App\Queries\M12\ReportData;
use App\Queries\M19\QuizAnalytics;
use App\Support\M03\AuthoringScope;
use App\Support\M10\QuizScore;
use App\Support\M19\Xlsx;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class QuizReportController
{
    public function show(Request $request, Course $course, ReportData $reports): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && AuthoringScope::isCurrentAuthor($user, $course), 403);

        $versions = $course->versions()->orderByDesc('version_no')->get();
        $versionId = $request->integer('version');
        $quizId = $request->integer('quiz');
        $version = $versionId > 0 ? $versions->firstWhere('id', $versionId) : $versions->first();
        abort_if($request->has('version') && ($versionId < 1 || $version === null), 403);
        $quizzes = $version?->quizzes()->whereNotNull('lesson_id')->with('lesson')->orderBy('id')->get() ?? collect();
        $quiz = $quizId > 0 ? $quizzes->firstWhere('id', $quizId) : $quizzes->first();
        abort_if($request->has('quiz') && ($quizId < 1 || $quiz === null), 403);
        $students = [];
        if ($quiz !== null && $quiz->lesson?->is_required === true) {
            $students = Enrollment::query()->where('course_id', $course->id)->where('course_version_id', $version?->id)
                ->with('user')->orderBy('id')->get()->map(function (Enrollment $enrollment) use ($course, $quiz) {
                    $attempts = QuizAttempts::for($enrollment, $quiz);
                    $used = (clone $attempts)->count();
                    $limit = QuizAttempts::limit($enrollment, $quiz);
                    $finalized = (clone $attempts)->whereIn('status', [AttemptStatus::DaNop, AttemptStatus::HetGio])
                        ->orderBy('attempt_no')->get()->map(fn (QuizAttempt $attempt) => [
                            'no' => $attempt->attempt_no,
                            'percent' => QuizScore::display(QuizScore::ratio($attempt)),
                            'url' => route('teacher.courses.attempts.show', ['course' => $course->id, 'attempt' => $attempt->id]),
                        ])->all();
                    $eligible = $limit > 0 && $used === $limit && ! $attempts->where(fn ($q) => $q
                        ->where('status', AttemptStatus::DangLam)->orWhere('passed', true))->exists();

                    return [
                        'id' => $enrollment->id, 'name' => $enrollment->user?->name,
                        'used' => $used, 'limit' => $limit, 'attempts' => $finalized,
                        'grant_url' => $eligible ? route('teacher.courses.enrollments.quizzes.extra-attempts.store',
                            ['course' => $course->id, 'enrollment' => $enrollment->id, 'quiz' => $quiz->id]) : null,
                    ];
                })->all();
        }

        return Inertia::render('teacher/courses/quiz-report', [
            'title' => $course->title,
            'url' => route('teacher.courses.quiz-report', $course),
            'versions' => $versions->map(fn (CourseVersion $row) => ['id' => $row->id, 'title' => 'Phiên bản '.$row->version_no]),
            'quizzes' => $quizzes->map(fn (Quiz $row) => ['id' => $row->id, 'title' => $row->lesson?->title]),
            'version' => $version?->id,
            'quiz' => $quiz?->id,
            'students' => $students,
            'analytics' => $quiz === null ? null : self::analytics($quiz, $course, $version),
            'export_url' => $quiz === null ? null : route('teacher.courses.quiz-report.export', ['course' => $course->id, 'quiz' => $quiz->id]),
            'summary' => $reports->section(fn () => $quiz === null ? null : $reports->latest('quiz_summary', 'QUIZ_PHIEN_BAN', $quiz->id)),
        ]);
    }

    /** QT-20: xuất Excel phân tích một quiz bài học (GV được giao). */
    public function export(Request $request, Course $course, Quiz $quiz): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && AuthoringScope::isCurrentAuthor($user, $course), 403);
        $version = CourseVersion::query()->whereKey($quiz->course_version_id)->where('course_id', $course->id)->first();
        abort_if($version === null || $quiz->lesson_id === null, 404);

        return Xlsx::download('phan-tich-quiz-'.$quiz->id.'.xlsx', QuizAnalytics::sheets(self::analytics($quiz, $course, $version)));
    }

    /** @return array{progress: array<string, int>, scores: array<string, string|int|null>, buckets: list<array{label: string, count: int}>, questions: list<array<string, mixed>>} */
    private static function analytics(Quiz $quiz, Course $course, ?CourseVersion $version): array
    {
        $assigned = Enrollment::query()->where('course_id', $course->id)->where('course_version_id', $version?->id)->count();

        return QuizAnalytics::build($quiz, QuizAttempt::query()->where('quiz_id', $quiz->id)->whereNotNull('enrollment_id'), $assigned);
    }
}
