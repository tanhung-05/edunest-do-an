<?php

namespace App\Http\Controllers\Admin;

use App\Enums\M01\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\M02\Category;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/** MH-QT-01 — tổng quan quản trị: lối tắt tài khoản, danh mục nền (nhật ký, doanh thu từ P4; CR-184). */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'locked' => User::query()->where('status', AccountStatus::BiKhoa->value)->count(),
                'categories' => Category::query()->count(),
            ],
        ]);
    }
}
