<?php

namespace App\Http\Controllers\Student;

use App\Enums\M08\EnrollmentValidity;
use App\Http\Controllers\Controller;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Queries\M06\PublicCatalog;
use App\Queries\M09\LearningEntry;
use App\Queries\M12\EnrollmentOverview;
use App\Support\M03\CourseCoverUrl;
use App\Support\M08\EnrollmentValidityChecker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-HV-01 bản P0 — trang bắt đầu Học viên: việc cần làm (xác minh email). Khối học tập từ P4. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $enrollments = Enrollment::query()->where('user_id', $user->id)->with('course.category:id,name')->latest('id')->get();
        $at = now();

        $progress = EnrollmentOverview::progressMany($enrollments);

        return Inertia::render('student/dashboard', [
            'courseCount' => $enrollments->count(),
            'needsEmailVerification' => $user->email_verified_at === null,
            // P10: gợi ý khóa nên học tiếp (cùng môn, nhiều người học).
            'suggestedCourses' => PublicCatalog::recommendFor($user),
            'learningCourses' => $enrollments->map(fn (Enrollment $enrollment) => [
                'id' => $enrollment->id,
                'title' => $enrollment->course->title,
                'course_id' => $enrollment->course_id,
                'cover' => CourseCoverUrl::for($enrollment->course),
                'validity' => EnrollmentValidityChecker::for($enrollment, $at)->value,
                'expires_at' => $enrollment->het_han_luc?->toISOString(),
                'expires_soon' => EnrollmentValidityChecker::for($enrollment, $at) === EnrollmentValidity::ConHan
                    && $enrollment->het_han_luc?->lte($at->addDays(7)) === true,
                'progress' => $progress[$enrollment->id],
                'learning_entry' => $entry = LearningEntry::for($user, $enrollment),
                // UAT 22/11: khóa đã học hết vẫn có nút ôn lại trên bảng điều khiển.
                'review_url' => $entry === null ? LearningEntry::review($user, $enrollment) : null,
            ]),
        ]);
    }
}
