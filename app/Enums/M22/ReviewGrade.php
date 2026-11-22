<?php

namespace App\Enums\M22;

/** Mức tự chấm thẻ ghi nhớ (M22). */
enum ReviewGrade: string
{
    case Quen = 'QUEN';
    case Kho = 'KHO';
    case Nho = 'NHO';
    case De = 'DE';

    public function label(): string
    {
        return match ($this) {
            self::Quen => 'Quên',
            self::Kho => 'Khó',
            self::Nho => 'Nhớ',
            self::De => 'Dễ',
        };
    }
}
