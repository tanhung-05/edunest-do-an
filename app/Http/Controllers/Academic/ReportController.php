<?php

namespace App\Http\Controllers\Academic;

use App\Models\M03\Course;
use App\Queries\M12\ReportData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ReportController
{
    public function index(Request $request, ReportData $reports): Response
    {
        Gate::authorize('view-revenue-report');
        $courses = Course::query()->orderBy('title')->get(['id', 'title']);
        $id = $request->integer('course');
        abort_if($request->has('course') && ! $courses->contains('id', $id), 403);

        return Inertia::render('academic/reports/index', [
            'courses' => $courses,
            'course' => $id ?: null,
            'revenue' => $reports->section(fn () => $reports->latest('revenue', 'TOAN_HE_THONG')),
            'orders' => $reports->section($reports->orders(...)),
            'enrollments' => $reports->section($reports->enrollments(...)),
            'completion' => $reports->section(fn () => $id > 0 ? $reports->latest('completion_rate', 'KHOA_HOC', $id) : null),
        ]);
    }
}
