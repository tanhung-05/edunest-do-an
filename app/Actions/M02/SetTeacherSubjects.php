<?php

namespace App\Actions\M02;

use App\Enums\M01\RoleCode;
use App\Models\M02\Category;
use App\Models\M02\TeacherProfile;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\Tx;

/**
 * CR-259 (UAT 22/11): Giáo vụ ghi chuyên môn (danh mục môn phụ trách) của giáo viên.
 * Giáo viên chưa có hồ sơ thì lập hồ sơ trống kèm chuyên môn. Khóa: users (người gọi → đích theo id) → teacher_profiles.
 */
final class SetTeacherSubjects
{
    /** @param list<int> $categoryIds */
    public function handle(User $actor, User $teacher, array $categoryIds): TeacherProfile
    {
        return Tx::run(function () use ($actor, $teacher, $categoryIds) {
            $ids = array_values(array_unique([$actor->id, $teacher->id]));
            sort($ids);
            $rows = User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            /** @var User $me */
            $me = $rows[$actor->id];
            /** @var User $t */
            $t = $rows[$teacher->id];
            if (! $me->hasEffectiveRole(RoleCode::GiaoVu)) {
                throw DomainRejection::forbidden('Chỉ Giáo vụ ghi chuyên môn giáo viên.');
            }
            if (! $t->hasRole(RoleCode::GiaoVien)) {
                throw new DomainRejection('Tài khoản đích không có vai Giáo viên.', 'general');
            }
            $wanted = array_values(array_unique($categoryIds));
            /** @var list<int> $valid */
            $valid = Category::query()->whereIn('id', $wanted)->orderBy('id')->pluck('id')
                ->map(fn ($id) => (int) $id)->values()->all();
            if (count($valid) !== count($wanted)) {
                throw new DomainRejection('Có môn không tồn tại.', 'subject_category_ids');
            }
            /** @var TeacherProfile|null $p */
            $p = TeacherProfile::query()->where('user_id', $t->id)->lockForUpdate()->first();
            $p ??= new TeacherProfile(['user_id' => $t->id, 'created_by_id' => $me->id]);
            $p->subject_category_ids = $valid === [] ? null : $valid;
            $p->save();

            return $p;
        });
    }
}
