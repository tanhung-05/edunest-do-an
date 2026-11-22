<?php

namespace App\Http\Controllers;

use App\Models\M05\CourseVersion;
use App\Queries\M05\VersionTree;
use App\Support\M05\StaffPreviewFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** QT-03 A3 (NH-61, CR-20): xem trước bản nháp như học viên; không ghi tiến độ, lượt, XP. */
class CoursePreviewController extends Controller
{
    public function __invoke(Request $request, CourseVersion $version): Response
    {
        Gate::authorize('preview', $version);
        $version->load('course');

        return Inertia::render('course-preview', [
            'course' => ['id' => $version->course->id, 'title' => $version->course->title],
            'version' => ['id' => $version->id, 'version_no' => $version->version_no, 'status_label' => $version->status->label()],
            'sections' => StaffPreviewFile::attach($request, $version, VersionTree::sections($version)),
        ]);
    }
}
