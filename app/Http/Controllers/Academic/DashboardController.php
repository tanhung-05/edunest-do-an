<?php

namespace App\Http\Controllers\Academic;

use App\Enums\M01\RoleCode;
use App\Http\Controllers\Controller;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use App\Models\User;
use App\Queries\M03\CourseList;
use App\Queries\M07\OrderView;
use App\Queries\M07\WorkQueues;
use App\Queries\M19\AcademicDashboard;
use App\Support\M19\Xlsx;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** MH-GVU-01 — tổng quan Giáo vụ: hàng chờ duyệt, khóa quá hạn soạn, đơn cần xử lý (P3), số liệu theo khoảng ngày (P6, QT-20). */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        [$start, $end] = AcademicDashboard::range($request->string('from')->toString() ?: null, $request->string('to')->toString() ?: null);

        return Inertia::render('academic/dashboard', [
            'stats' => AcademicDashboard::build($start, $end),
            'export_url' => route('academic.dashboard.export', ['from' => $start->toDateString(), 'to' => $end->toDateString()]),
            'ordersNeedingAttention' => WorkQueues::orders()->limit(10)->get()->map(fn (Order $order) => OrderView::row($order, true)),
            'paymentsNeedingRefund' => WorkQueues::refunds()->limit(10)->get()->map(fn (Payment $payment) => OrderView::payment($payment, $payment->order)),
            'reviewQueue' => CourseList::reviewQueue(),
            'overdueCourses' => array_values(array_filter(CourseList::forAcademic(), fn (array $c) => $c['overdue'] === true)),
            'teacherCount' => User::query()->whereHas('roles', fn ($q) => $q->where('code', RoleCode::GiaoVien->value))->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$start, $end] = AcademicDashboard::range($request->string('from')->toString() ?: null, $request->string('to')->toString() ?: null);

        return Xlsx::download('tong-quan-'.$start->toDateString().'_'.$end->toDateString().'.xlsx', AcademicDashboard::sheets(AcademicDashboard::build($start, $end)));
    }
}
