<?php

namespace App\Events\M10;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class AttemptFinalized implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $attemptId) {}
}
