<?php

namespace App\Queries\M06;

use App\Models\M08\Enrollment;

/** BR-BC-01: số học viên khác nhau có ghi danh. */
final class PublicStats
{
    public static function students(): int
    {
        return Enrollment::query()->distinct()->count('user_id');
    }
}
