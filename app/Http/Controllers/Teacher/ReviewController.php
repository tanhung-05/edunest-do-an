<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\M14\CourseRatings;
use App\Enums\M14\RatingStatus;
use App\Http\Controllers\Controller;
use App\Models\M03\CourseAuthor;
use App\Models\M14\CourseRating;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-GV-12 (QT-16 b4–5, A6). */
final class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $courseIds = CourseAuthor::query()->where('user_id', $user->id)->whereNotNull('current_key')->pluck('course_id');

        return Inertia::render('teacher/reviews/index', [
            'ratings' => CourseRating::query()->whereIn('course_id', $courseIds)->where('status', RatingStatus::Hien)
                ->with(['user:id,name', 'course:id,title'])->latest('id')->limit(200)->get()->map(fn (CourseRating $r) => [
                    'id' => $r->id, 'student' => $r->user->name, 'course' => $r->course->title, 'stars' => $r->stars,
                    'comment' => $r->comment, 'reply' => $r->reply, 'created_at' => $r->created_at?->toIso8601String(),
                ])->values(),
        ]);
    }

    public function reply(Request $request, CourseRating $rating, CourseRatings $ratings): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $data = $request->validate(['reply' => ['required', 'string', 'max:2000']]);
        $ratings->reply($user, $rating, $data['reply']);

        return back()->with('status', 'Đã trả lời đánh giá.');
    }
}
