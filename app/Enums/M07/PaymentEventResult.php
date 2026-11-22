<?php

namespace App\Enums\M07;

/** Kết quả trong xác nhận. */
enum PaymentEventResult: string
{
    case ThanhCong = 'THANH_CONG';
    case ThatBai = 'THAT_BAI';

    public function label(): string
    {
        return match ($this) {
            self::ThanhCong => 'Thành công',
            self::ThatBai => 'Thất bại',
        };
    }
}
