<?php

namespace App\Support\M08;

use App\Enums\M08\EnrollmentValidity;
use App\Models\M08\Enrollment;
use Carbon\CarbonImmutable;

final class EnrollmentValidityChecker
{
    public static function for(Enrollment $enrollment, ?CarbonImmutable $at = null): EnrollmentValidity
    {
        return match (true) {
            $enrollment->revoked_at !== null => EnrollmentValidity::DaThuHoi,
            $enrollment->het_han_luc === null, ($at ?? now())->lt($enrollment->het_han_luc) => EnrollmentValidity::ConHan,
            default => EnrollmentValidity::HetHan,
        };
    }
}
