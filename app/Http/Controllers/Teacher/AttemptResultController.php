<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\M10\AttemptStatus;
use App\Models\M03\Course;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Queries\M10\AttemptDetails;
use App\Support\M03\AuthoringScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * NH-27, NH-31 (Giáo viên: khóa được giao) — đọc kết quả, đáp án và lời giải của một lượt đã chốt.
 * Chỉ đọc; tách khỏi thao tác cấp thêm lượt (NH-51). AC-10-56: giáo viên không được giao khóa bị 403.
 */
final class AttemptResultController
{
    public function show(Request $request, Course $course, QuizAttempt $attempt): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && AuthoringScope::isCurrentAuthor($user, $course), 403);
        $enrollment = $attempt->enrollment;
        abort_unless($enrollment !== null && $enrollment->course_id === $course->id, 403);
        abort_if($attempt->status === AttemptStatus::DangLam, 403);
        $quiz = $attempt->quiz()->firstOrFail();

        return Inertia::render('teacher/courses/attempt-result', [
            'course' => $course->title,
            'student' => $attempt->user?->name,
            'lesson' => $quiz->lesson?->title,
            'attempt' => AttemptDetails::summary($attempt),
            'questions' => AttemptDetails::questions($attempt, true),
            'back_url' => route('teacher.courses.quiz-report', ['course' => $course->id, 'quiz' => $quiz->id]),
        ]);
    }
}
