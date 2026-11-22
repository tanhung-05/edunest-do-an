<?php

namespace App\Enums\M04;

/** 02 L23 loại quiz. */
enum QuizKind: string
{
    case BaiHoc = 'BAI_HOC';
    case ThiThu = 'THI_THU';

    public function label(): string
    {
        return match ($this) {
            self::BaiHoc => 'Quiz bài học',
            self::ThiThu => 'Thi thử',
        };
    }
}
