<?php

namespace App\Events\M01;

use Illuminate\Foundation\Events\Dispatchable;

/** SK-11 — phát sau commit của LockAccount (03 §14). P4 nối chốt lượt quiz. */
final class AccountLocked
{
    use Dispatchable;

    public function __construct(public readonly int $userId, public readonly int $lockedById) {}
}
