<?php

namespace App\Enums\M07;

/** 03 §7b đối soát. */
enum PaymentReconcileStatus: string
{
    case HopLe = 'HOP_LE';
    case CanHoan = 'CAN_HOAN';
    case DaHoanLai = 'DA_HOAN_LAI';

    public function label(): string
    {
        return match ($this) {
            self::HopLe => 'Hợp lệ',
            self::CanHoan => 'Cần hoàn',
            self::DaHoanLai => 'Đã hoàn',
        };
    }
}
