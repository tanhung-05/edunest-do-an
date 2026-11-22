<?php

namespace App\Support\M09;

use App\Models\M08\Enrollment;
use App\Models\User;
use App\Support\DomainRejection;

final class LearningLocks
{
    /** @return array{User, Enrollment} */
    public static function student(User $user, Enrollment $enrollment): array
    {
        $locked = User::query()->whereKey($user->id)->sharedLock()->firstOrFail();
        // TK-3/TK-4, CR-04: yêu cầu mang thế hệ xác thực cũ (khóa rồi mở lại giữa chừng) bị từ chối dưới khóa.
        if (! $locked->isActive() || $locked->auth_generation !== $user->auth_generation) {
            throw DomainRejection::forbidden('Phiên đăng nhập không còn hiệu lực.');
        }

        return [$locked, Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail()];
    }
}
