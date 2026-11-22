<?php

namespace App\Http\Controllers\Admin;

use App\Queries\M12\ReportData;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class RevenueController
{
    public function index(ReportData $reports): Response
    {
        Gate::authorize('view-revenue-report');

        return Inertia::render('admin/revenue/index', ['revenue' => $reports->section(fn () => $reports->latest('revenue', 'TOAN_HE_THONG'))]);
    }
}
