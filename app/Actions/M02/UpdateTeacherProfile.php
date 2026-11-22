<?php

namespace App\Actions\M02;

use App\Enums\M01\RoleCode;
use App\Models\M02\TeacherProfile;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\Tx;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * NH-54 update — Giáo viên sửa hồ sơ hiện có của chính mình (QT-02 b6–7, CR-08).
 * Khóa: users (người gọi, đọc lại vai) → teacher_profiles.
 */
final class UpdateTeacherProfile
{
    /** @param array{bio?: ?string, achievements?: ?string} $data */
    public function handle(User $actor, array $data, ?UploadedFile $photo = null): TeacherProfile
    {
        $newPath = $photo?->store('teacher-photos', 'public');
        $old = null;

        $profile = Tx::run(function () use ($actor, $data, $newPath, &$old) {
            /** @var User $me */
            $me = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if (! $me->hasEffectiveRole(RoleCode::GiaoVien)) {
                throw DomainRejection::forbidden('Chỉ Giáo viên sửa được hồ sơ của mình.');
            }
            /** @var TeacherProfile|null $p */
            $p = TeacherProfile::query()->where('user_id', $me->id)->lockForUpdate()->first();
            if ($p === null) {
                throw DomainRejection::forbidden('Bạn chưa có hồ sơ giáo viên. Hãy liên hệ Giáo vụ.');
            }
            $p->bio = $data['bio'] ?? null;
            $p->achievements = $data['achievements'] ?? null;
            if (is_string($newPath)) {
                $old = $p->photo_path;
                $p->photo_path = $newPath;
            }
            $p->save();

            return $p;
        });

        if ($old !== null) {
            Storage::disk('public')->delete($old);
        }

        return $profile;
    }
}
