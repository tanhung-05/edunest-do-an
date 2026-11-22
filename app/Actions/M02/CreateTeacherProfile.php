<?php

namespace App\Actions\M02;

use App\Enums\M01\RoleCode;
use App\Models\M02\TeacherProfile;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\Tx;
use Illuminate\Database\QueryException;

/**
 * NH-54 create — chỉ Giáo vụ lập hồ sơ cho tài khoản Giáo viên chưa có hồ sơ (ví dụ do Quản trị cấp).
 * Luồng chính tạo hồ sơ cùng TK-2 trong ProvisionAccount. Khóa: users (người gọi → đích theo id) → teacher_profiles.
 */
final class CreateTeacherProfile
{
    /** @param array{bio?: ?string, achievements?: ?string} $data */
    public function handle(User $actor, User $teacher, array $data): TeacherProfile
    {
        try {
            return Tx::run(function () use ($actor, $teacher, $data) {
                $ids = [$actor->id, $teacher->id];
                sort($ids);
                $rows = User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                /** @var User $me */
                $me = $rows[$actor->id];
                /** @var User $t */
                $t = $rows[$teacher->id];
                if (! $me->hasEffectiveRole(RoleCode::GiaoVu)) {
                    throw DomainRejection::forbidden('Chỉ Giáo vụ lập hồ sơ giáo viên.');
                }
                if (! $t->hasRole(RoleCode::GiaoVien)) {
                    throw new DomainRejection('Tài khoản đích không có vai Giáo viên.', 'general');
                }
                if (TeacherProfile::query()->where('user_id', $t->id)->exists()) {
                    throw new DomainRejection('Giáo viên đã có hồ sơ.', 'general');
                }

                return TeacherProfile::query()->create([
                    'user_id' => $t->id,
                    'bio' => $data['bio'] ?? null,
                    'achievements' => $data['achievements'] ?? null,
                    'created_by_id' => $me->id,
                ]);
            });
        } catch (QueryException $e) {
            if (Tx::code($e) === 1062) {
                throw new DomainRejection('Giáo viên đã có hồ sơ.', 'general');
            }
            throw $e;
        }
    }
}
