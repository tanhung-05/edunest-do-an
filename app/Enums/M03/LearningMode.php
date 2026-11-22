<?php

namespace App\Enums\M03;

/** 02 L10 kiểu học. */
enum LearningMode: string
{
    case TuanTu = 'TUAN_TU';
    case TuDo = 'TU_DO';

    public function label(): string
    {
        return match ($this) {
            self::TuanTu => 'Tuần tự',
            self::TuDo => 'Tự do',
        };
    }
}
