<?php

namespace App\Http\Controllers\Academic;

use App\Actions\M10\GrantExtraQuizAttempt;
use App\Models\M04\Quiz;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Queries\M12\EnrollmentOverview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EnrollmentController
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());
        $course = $request->integer('course');
        $rows = Enrollment::query()->with(['user', 'course', 'version'])
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))))
            ->when($course > 0, fn ($q) => $q->where('course_id', $course))
            ->latest('id')->paginate(30)->withQueryString();
        $progress = EnrollmentOverview::progressMany($rows->items());
        $rows->through(fn (Enrollment $enrollment) => EnrollmentOverview::row($enrollment, true, $progress[$enrollment->id] ?? null));

        return Inertia::render('academic/enrollments/index', ['enrollments' => $rows, 'q' => $search]);
    }

    public function grant(Request $request, Enrollment $enrollment, Quiz $quiz, GrantExtraQuizAttempt $grant): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $grant->handle($actor, $enrollment, $quiz, (string) $data['reason']);

        return back()->with('status', 'Đã cấp thêm một lượt.');
    }
}
