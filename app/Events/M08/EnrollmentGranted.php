<?php

namespace App\Events\M08;

use App\Models\M08\Enrollment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class EnrollmentGranted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Enrollment $enrollment) {}
}
