<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\M03\CourseList;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-GV-01 — việc cần làm của Giáo viên: khóa đang soạn, quá hạn, bản bị trả lại kèm lý do (AC-03-42/57). */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('teacher/dashboard', [
            'hasProfile' => $user->teacherProfile()->exists(),
            'courses' => CourseList::forAuthor($user),
        ]);
    }
}
