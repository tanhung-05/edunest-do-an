<?php

namespace App\Events\M05;

use Illuminate\Foundation\Events\Dispatchable;

/** SK-05 bản gửi duyệt (PB-3), phát sau commit. */
final class CourseVersionSubmitted
{
    use Dispatchable;

    public function __construct(public readonly int $versionId) {}
}
