<?php

namespace App\Events\M05;

use App\Enums\M05\ReviewResult;
use Illuminate\Foundation\Events\Dispatchable;

/** SK-06 kết quả duyệt (PB-5/PB-7), phát sau commit. */
final class CourseVersionReviewed
{
    use Dispatchable;

    public function __construct(public readonly int $versionId, public readonly ReviewResult $result) {}
}
