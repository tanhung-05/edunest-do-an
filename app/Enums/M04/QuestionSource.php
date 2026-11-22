<?php

namespace App\Enums\M04;

/** 02 L20 nguồn câu. */
enum QuestionSource: string
{
    case GiaoVien = 'GIAO_VIEN';
    case Ai = 'AI';

    public function label(): string
    {
        return match ($this) {
            self::GiaoVien => 'Giáo viên',
            self::Ai => 'AI',
        };
    }
}
