<?php

namespace App\Enums\M07;

/** Nguồn đơn. */
enum OrderSource: string
{
    case MuaNgay = 'MUA_NGAY';
    case GiaHan = 'GIA_HAN';
    case GioHang = 'GIO_HANG';
    case Combo = 'COMBO';

    public function label(): string
    {
        return match ($this) {
            self::MuaNgay => 'Mua ngay',
            self::GiaHan => 'Gia hạn',
            self::GioHang => 'Giỏ hàng',
            self::Combo => 'Combo',
        };
    }
}
