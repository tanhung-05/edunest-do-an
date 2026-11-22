<?php

namespace App\Http\Controllers\Academic;

use App\Actions\M05\VersionTransitions;
use App\Http\Controllers\Controller;
use App\Models\M05\CourseVersion;
use App\Queries\M05\VersionDiff;
use App\Queries\M05\VersionTree;
use App\Support\M03\ReviewerIndependence;
use App\Support\M05\StaffPreviewFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** MH-GVU-05/06: xem bản ở chế độ chỉ đọc, Duyệt/Trả lại (E6) và Công bố. */
class CourseVersionController extends Controller
{
    public function show(Request $request, CourseVersion $version): Response
    {
        Gate::authorize('view', $version);
        $actor = $request->user();
        $version->load('course');

        return Inertia::render('academic/versions/show', [
            'course' => ['id' => $version->course->id, 'title' => $version->course->title, 'sale_status_label' => $version->course->sale_status->label()],
            'version' => [
                'id' => $version->id, 'version_no' => $version->version_no,
                'status' => $version->status->value, 'status_label' => $version->status->label(),
            ],
            'sections' => StaffPreviewFile::attach($request, $version, VersionTree::sections($version)),
            'reviews' => VersionTree::reviews($version),
            'diff' => VersionDiff::against($version),
            'can' => [
                'review' => $actor->can('approve', $version) && $version->status->value === 'CHO_DUYET',
                'publish' => $actor->can('publish', $version) && $version->status->value === 'DA_DUYET',
            ],
            'independence' => ReviewerIndependence::violation($actor, $version),
        ]);
    }

    public function approve(Request $request, CourseVersion $version, VersionTransitions $action): RedirectResponse
    {
        Gate::authorize('approve', $version);
        $actor = $request->user();
        $action->approve($actor, $version);

        return back()->with('status', 'Đã duyệt bản.');
    }

    public function returnVersion(Request $request, CourseVersion $version, VersionTransitions $action): RedirectResponse
    {
        Gate::authorize('return', $version);
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'comments' => ['nullable', 'array'],
            'comments.*' => ['nullable', 'string', 'max:5000'],
        ]);
        $actor = $request->user();
        /** @var array<int|string, string> $comments */
        $comments = array_filter($data['comments'] ?? [], fn ($v) => is_string($v));
        $action->returnVersion($actor, $version, $data['reason'] ?? null, $comments);

        return back()->with('status', 'Đã trả lại bản.');
    }

    public function publish(Request $request, CourseVersion $version, VersionTransitions $action): RedirectResponse
    {
        Gate::authorize('publish', $version);
        $actor = $request->user();
        $action->publish($actor, $version);

        return back()->with('status', 'Đã công bố bản.');
    }
}
