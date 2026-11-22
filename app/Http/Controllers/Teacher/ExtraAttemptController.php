<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\M10\GrantExtraQuizAttempt;
use App\Models\M03\Course;
use App\Models\M04\Quiz;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Support\M03\AuthoringScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ExtraAttemptController
{
    public function store(Request $request, Course $course, Enrollment $enrollment, Quiz $quiz, GrantExtraQuizAttempt $grant): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $enrollment->course_id === $course->id && AuthoringScope::isCurrentAuthor($actor, $course), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $grant->handle($actor, $enrollment, $quiz, (string) $data['reason']);

        return back()->with('status', 'Đã cấp thêm một lượt.');
    }
}
