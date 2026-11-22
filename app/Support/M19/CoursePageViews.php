<?php

namespace App\Support\M19;

use App\Models\M03\Course;
use App\Models\M19\CoursePageView;
use Illuminate\Http\Request;

/** CR-113: một lượt xem cho mỗi bộ phiên–ngày (giờ VN)–khóa; lưu băm phiên, không lưu định danh người xem. */
final class CoursePageViews
{
    public static function record(Request $request, Course $course): void
    {
        if (! $request->hasSession()) {
            return;
        }
        $now = now();
        CoursePageView::query()->insertOrIgnore([
            'course_id' => $course->id,
            'session_hash' => hash('sha256', $request->session()->getId()),
            'view_date' => $now->copy()->setTimezone('Asia/Ho_Chi_Minh')->toDateString(),
            'created_at' => $now,
        ]);
    }
}
