<?php

namespace App\Enums\M05;

/** 02 L17 kết quả duyệt. */
enum ReviewResult: string
{
    case Duyet = 'DUYET';
    case TraLai = 'TRA_LAI';

    public function label(): string
    {
        return match ($this) {
            self::Duyet => 'Duyệt',
            self::TraLai => 'Trả lại',
        };
    }
}
