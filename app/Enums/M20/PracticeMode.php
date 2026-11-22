<?php

namespace App\Enums\M20;

/** Chế độ phiên luyện tập (M20). */
enum PracticeMode: string
{
    case Learning = 'LEARNING';
    case TryHard = 'TRY_HARD';

    public function label(): string
    {
        return match ($this) {
            self::Learning => 'Learning',
            self::TryHard => 'Try Hard',
        };
    }
}
