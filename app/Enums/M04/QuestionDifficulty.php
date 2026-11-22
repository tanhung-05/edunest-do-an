<?php

namespace App\Enums\M04;

/** 02 L20 độ khó. */
enum QuestionDifficulty: string
{
    case De = 'DE';
    case TrungBinh = 'TRUNG_BINH';
    case Kho = 'KHO';

    public function label(): string
    {
        return match ($this) {
            self::De => 'Dễ',
            self::TrungBinh => 'Trung bình',
            self::Kho => 'Khó',
        };
    }
}
