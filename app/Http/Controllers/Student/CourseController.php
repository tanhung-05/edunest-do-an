<?php

namespace App\Http\Controllers\Student;

use App\Enums\M08\EnrollmentValidity;
use App\Http\Controllers\Controller;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Queries\M09\LearningEntry;
use App\Queries\M12\EnrollmentOverview;
use App\Support\M03\CourseCoverUrl;
use App\Support\M08\EnrollmentValidityChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CourseController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $courses = Enrollment::query()->where('user_id', $user->id)
            ->with(['course.category:id,name', 'version'])->latest('id')->get();
        $at = now();
        $selected = $request->integer('course');
        $target = $courses->firstWhere('course_id', $selected);
        if ($target !== null && ($entry = LearningEntry::for($user, $target)) !== null) {
            // CTA mua và vào học đã truyền mã khóa từ P3 (CR-205).
            return redirect()->to($entry['url']);
        }

        $progressAll = EnrollmentOverview::progressMany($courses);

        return Inertia::render('student/courses/index', [
            'selectedCourse' => $selected ?: null,
            'courses' => $courses->map(function (Enrollment $enrollment) use ($at, $user, $progressAll) {
                $validity = EnrollmentValidityChecker::for($enrollment, $at);
                $progress = $progressAll[$enrollment->id];

                return [
                    'id' => $enrollment->id,
                    'course_id' => $enrollment->course_id,
                    'title' => $enrollment->course->title,
                    'cover' => CourseCoverUrl::for($enrollment->course),
                    'version' => $enrollment->version->version_no,
                    'learning_status' => $enrollment->learning_status->label(),
                    'validity' => $validity->value,
                    'validity_label' => $validity->label(),
                    'expires_at' => $enrollment->het_han_luc?->toISOString(),
                    'expires_soon' => $validity === EnrollmentValidity::ConHan && $enrollment->het_han_luc?->lte($at->addDays(7)) === true,
                    'progress' => $progress,
                    'learning_entry' => $entry = LearningEntry::for($user, $enrollment),
                    'review_url' => $entry === null ? LearningEntry::review($user, $enrollment) : null,
                    // P5 — QT-15 b7, A4: Gia hạn cho ghi danh có hạn, không thu hồi, khóa có giá gia hạn.
                    'renew_url' => $validity !== EnrollmentValidity::DaThuHoi && $enrollment->het_han_luc !== null
                        && $enrollment->course->extension_price !== null ? route('student.enrollments.renew', $enrollment) : null,
                    'extension_price' => $enrollment->course->extension_price,
                ];
            })->all(),
        ]);
    }
}
