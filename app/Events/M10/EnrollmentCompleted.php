<?php

namespace App\Events\M10;

use Illuminate\Foundation\Events\Dispatchable;

final class EnrollmentCompleted
{
    use Dispatchable;

    public function __construct(public readonly int $enrollmentId, public readonly string $completionEventId) {}
}
