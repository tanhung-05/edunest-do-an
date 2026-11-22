<?php

namespace App\Events\M09;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class LessonCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $enrollmentId, public readonly int $lessonId) {}
}
