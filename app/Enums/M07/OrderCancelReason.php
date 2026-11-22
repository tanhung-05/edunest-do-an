<?php

namespace App\Enums\M07;

/** 03 §6 lý do hủy đơn. */
enum OrderCancelReason: string
{
    case HocVienHuy = 'HOC_VIEN_HUY';
    case HuySauHoan = 'HUY_SAU_HOAN';

    public function label(): string
    {
        return match ($this) {
            self::HocVienHuy => 'Học viên hủy',
            self::HuySauHoan => 'Hủy sau hoàn',
        };
    }
}
