<?php

namespace App\Enums\M14;

/** Đánh giá khóa (02b DG-1..4). */
enum RatingStatus: string
{
    case Hien = 'HIEN';
    case DaAn = 'DA_AN';

    public function label(): string
    {
        return match ($this) {
            self::Hien => 'Đang hiển thị',
            self::DaAn => 'Đã ẩn',
        };
    }
}
