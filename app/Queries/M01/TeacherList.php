<?php

namespace App\Queries\M01;

use App\Enums\M01\RoleCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * NH-87 (CR-191): danh sách Giáo viên cho Giáo vụ — chỉ họ tên, email, trạng thái, số khóa đang soạn.
 * Số khóa đang soạn đọc từ course_authors khi có ở P1 (P0 = 0).
 */
final class TeacherList
{
    /** @return list<array{id: int, name: string, email: string, status: string, status_label: string, authoring_count: int, has_profile: bool, subject_ids: list<int>}> */
    public function get(string $search = ''): array
    {
        $q = User::query()
            ->whereHas('roles', fn ($r) => $r->where('code', RoleCode::GiaoVien->value))
            ->withExists('teacherProfile')
            ->with('teacherProfile:id,user_id,subject_category_ids')
            ->addSelect(['authoring_count' => DB::table('course_authors')->selectRaw('count(*)')
                ->whereColumn('course_authors.user_id', 'users.id')->whereNotNull('current_key')])
            ->orderBy('name');
        if ($search !== '') {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return array_values($q->limit(200)->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'status' => $u->status->value,
            'status_label' => $u->status->label(),
            'authoring_count' => (int) $u->getAttribute('authoring_count'),
            'has_profile' => (bool) $u->getAttribute('teacher_profile_exists'),
            'subject_ids' => $u->teacherProfile->subject_category_ids ?? [],
        ])->all());
    }
}
