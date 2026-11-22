<?php

namespace App\Http\Controllers\Academic;

use App\Enums\M01\RoleCode;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Queries\M12\EnrollmentOverview;
use Inertia\Inertia;
use Inertia\Response;

final class StudentController
{
    public function show(User $user): Response
    {
        abort_unless($user->hasRole(RoleCode::HocVien), 404);

        return Inertia::render('academic/students/show', [
            'student' => ['name' => $user->name, 'email' => $user->email, 'status' => $user->status->label()],
            'enrollments' => Enrollment::query()->where('user_id', $user->id)->with(['user', 'course', 'version'])
                ->latest('id')->get()->map(fn (Enrollment $enrollment) => EnrollmentOverview::row($enrollment)),
        ]);
    }
}
