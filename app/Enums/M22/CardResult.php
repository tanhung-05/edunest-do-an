<?php

namespace App\Enums\M22;

/** Kết quả một lượt thẻ trong game (BR-XP-69). */
enum CardResult: string
{
    case Dung = 'DUNG';
    case Sai = 'SAI';
    case ChamDay = 'CHAM_DAY';

    public function label(): string
    {
        return match ($this) {
            self::Dung => 'Đúng',
            self::Sai => 'Sai',
            self::ChamDay => 'Chạm đáy',
        };
    }
}
