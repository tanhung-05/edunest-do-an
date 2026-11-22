<?php

namespace App\Events\M05;

use Illuminate\Foundation\Events\Dispatchable;

/** SK-07 bản công bố (PB-8), phát sau commit. */
final class CourseVersionPublished
{
    use Dispatchable;

    public function __construct(public readonly int $versionId) {}
}
