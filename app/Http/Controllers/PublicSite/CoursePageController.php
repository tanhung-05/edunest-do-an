<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\M01\RoleCode;
use App\Enums\M03\SaleStatus;
use App\Enums\M07\OrderStatus;
use App\Enums\M08\EnrollmentValidity;
use App\Http\Controllers\Controller;
use App\Models\M05\CourseSection;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Queries\M07\OpenOrders;
use App\Support\M03\CourseCoverUrl;
use App\Support\M06\PublicLessonAccess;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M12\CommerceView;
use App\Support\M14\RatingView;
use App\Support\M19\CoursePageViews;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MH-KH-03 (QT-06 b5–7, A2, A6, E7): trang khóa đọc bản `DA_CONG_BO` hiện hành. Bài trả phí chỉ có tên và
 * thời lượng. Nhánh ghi danh/đơn của người xem bật ở P3–P4.
 */
class CoursePageController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $course = PublicLessonAccess::course($slug);
        // QT-26 bước 13–15a: tham số giới thiệu của trang, chuyển tiếp tới thao tác đặt.
        $ref = is_string($request->query('ref')) && preg_match('/^[a-z0-9-]{3,32}$/i', $request->query('ref')) === 1 ? $request->query('ref') : null;
        $course->load(['currentAuthor.user.teacherProfile', 'category:id,name', 'level:id,name']);
        CoursePageViews::record($request, $course);
        /** @var CourseVersion $version */
        $version = CourseVersion::query()->findOrFail($course->current_version_id);
        $sections = CourseSection::query()->where('course_version_id', $version->id)->whereNull('removed_at')->orderBy('position')->orderBy('id')->get();
        $lessons = Lesson::query()->where('course_version_id', $version->id)->whereNull('removed_at')
            ->whereIn('course_section_id', $sections->pluck('id'))->orderBy('position')->orderBy('id')->get();
        $n = 0;
        $totalMs = 0;
        $outline = [];
        foreach ($sections as $s) {
            $items = [];
            foreach ($lessons->where('course_section_id', $s->id) as $l) {
                $totalMs += (int) $l->video_duration_ms;
                $items[] = [
                    'id' => $l->id,
                    'number' => ++$n,
                    'title' => $l->title,
                    'type_label' => $l->type->label(),
                    'minutes' => $l->video_duration_ms !== null ? (int) round($l->video_duration_ms / 60000) : null,
                    'is_preview' => $l->is_preview,
                    'preview_url' => $l->is_preview ? route('public.lessons.preview', [$course->slug, $l->id]) : null,
                ];
            }
            $outline[] = ['id' => $s->id, 'title' => $s->title, 'lessons' => $items];
        }
        $author = $course->currentAuthor?->user;
        $cover = CourseCoverUrl::for($course);
        $selling = $course->sale_status === SaleStatus::DangBan;
        $user = $request->user();
        $student = $user instanceof User && $user->hasEffectiveRole(RoleCode::HocVien) ? $user : null;
        $enrollment = $student === null ? null : Enrollment::query()->where('user_id', $student->id)->where('course_id', $course->id)->first();
        $open = $student === null || $enrollment !== null ? null : OpenOrders::current($student->id, [$course->id]);
        $validity = $enrollment === null ? null : EnrollmentValidityChecker::for($enrollment);
        [$label, $url] = match (true) {
            $validity === EnrollmentValidity::ConHan => ['Vào học', route('student.courses.index', ['course' => $course->id])],
            // CR-118: từ P5 ghi danh hết hạn có nút "Gia hạn" khi khóa có giá gia hạn.
            $validity === EnrollmentValidity::HetHan && $course->extension_price !== null && $enrollment->het_han_luc !== null => ['Gia hạn', null],
            $validity === EnrollmentValidity::HetHan => ['Đã hết hạn truy cập', null],
            $validity === EnrollmentValidity::DaThuHoi => ['Liên hệ giáo vụ', null],
            $open?->status === OrderStatus::ChoThanhToan => ['Tiếp tục thanh toán', route('student.orders.show', $open)],
            $open !== null => ['Xem đơn hàng', route('student.orders.show', $open)],
            $selling => ['Mua ngay', route('public.courses.buy', array_filter(['slug' => $course->slug, 'ref' => $ref]))],
            default => [null, null],
        };

        return Inertia::render('public/courses/show', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'cover' => $cover,
                'category' => $course->category->name,
                'level' => $course->level->name,
                'summary' => $version->summary,
                'objectives' => $version->objectives,
                'description' => $version->description,
                'price' => $course->price,
                'original_price' => $course->original_price !== null && $course->original_price > $course->price ? $course->original_price : null,
                'access_months' => $course->access_months,
                'sale_status' => $course->sale_status->value,
                'sale_status_label' => $course->sale_status->label(),
                'lesson_count' => $n,
                'total_minutes' => $totalMs > 0 ? (int) round($totalMs / 60000) : null,
                'student_count' => Enrollment::query()->where('course_id', $course->id)->count(),
            ],
            'pricing' => CommerceView::pricing($course),
            'ratings' => RatingView::forCourse($course, $user instanceof User ? $user : null, $enrollment),
            'teacher' => $author === null ? null : ['id' => $author->id, 'name' => $author->name, 'bio' => $author->teacherProfile?->bio],
            'outline' => $outline,
            'cta' => [
                'buy' => $selling && $enrollment === null,
                'label' => $label,
                'url' => $url,
                'validity' => $validity?->value,
                'renew_url' => $enrollment !== null && $validity !== EnrollmentValidity::DaThuHoi && $enrollment->het_han_luc !== null
                    && $course->extension_price !== null ? route('student.enrollments.renew', $enrollment) : null,
                'extension_price' => $course->extension_price,
                'cart_url' => $selling && $enrollment === null && $student !== null ? route('student.cart.store', $course) : null,
                'wishlist_url' => $student !== null ? route('student.wishlist.toggle', $course) : null,
                ...CommerceView::state($student, $course),
                'login_url' => $request->user() === null ? route('login', ['redirect' => route('public.courses.show', array_filter(['slug' => $course->slug, 'ref' => $ref]), false)]) : null,
            ],
            'seo' => [
                'title' => $version->seo_title ?: $course->title,
                'description' => $version->seo_description ?: ($version->summary ?: 'Khóa học '.$course->title.' trên EduNest.'),
                'image' => $cover,
                'url' => route('public.courses.show', $course->slug),
            ],
        ]);
    }
}
