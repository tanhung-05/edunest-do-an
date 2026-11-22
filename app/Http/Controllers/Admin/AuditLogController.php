<?php

namespace App\Http\Controllers\Admin;

use App\Models\M11\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class AuditLogController
{
    public function index(Request $request): Response
    {
        Gate::authorize('view-audit-logs');
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'], 'actor_id' => ['nullable', 'integer', 'min:1'],
            'subject_type' => ['nullable', 'string', 'max:100'], 'subject_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $logs = AuditLog::query();
        foreach (['action', 'actor_id', 'subject_type', 'subject_id'] as $column) {
            if (isset($filters[$column])) {
                $logs->where($column, $filters[$column]);
            }
        }

        return Inertia::render('admin/audit-logs/index', [
            'logs' => $logs->orderByDesc('created_at')->orderByDesc('id')->paginate(30)->withQueryString(),
            'filters' => $filters,
        ]);
    }
}
