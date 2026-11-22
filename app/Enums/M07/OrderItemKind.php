<?php

namespace App\Enums\M07;

/** Loại dòng đơn. */
enum OrderItemKind: string
{
    case MuaKhoa = 'MUA_KHOA';
    case GiaHan = 'GIA_HAN';

    public function label(): string
    {
        return match ($this) {
            self::MuaKhoa => 'Mua khóa',
            self::GiaHan => 'Gia hạn',
        };
    }
}
