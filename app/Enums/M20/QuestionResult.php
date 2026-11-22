<?php

namespace App\Enums\M20;

/** Kết quả một câu luyện tập. */
enum QuestionResult: string
{
    case Dung = 'DUNG';
    case Sai = 'SAI';
    case BoTrong = 'BO_TRONG';
    case HetGio = 'HET_GIO';

    public function label(): string
    {
        return match ($this) {
            self::Dung => 'Đúng',
            self::Sai => 'Sai',
            self::BoTrong => 'Bỏ trống',
            self::HetGio => 'Hết giờ',
        };
    }
}
