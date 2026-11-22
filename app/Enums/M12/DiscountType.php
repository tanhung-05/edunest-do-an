<?php

namespace App\Enums\M12;

/** Loại mã giảm giá (02b N01). */
enum DiscountType: string
{
    case PhanTram = 'PHAN_TRAM';
    case SoTien = 'SO_TIEN';

    public function label(): string
    {
        return match ($this) {
            self::PhanTram => 'Phần trăm',
            self::SoTien => 'Số tiền',
        };
    }
}
