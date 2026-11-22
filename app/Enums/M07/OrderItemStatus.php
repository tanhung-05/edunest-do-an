<?php

namespace App\Enums\M07;

/** 03 §8 trạng thái dòng đơn. */
enum OrderItemStatus: string
{
    case ChuaCap = 'CHUA_CAP';
    case DaCap = 'DA_CAP';
    case CapLoi = 'CAP_LOI';

    public function label(): string
    {
        return match ($this) {
            self::ChuaCap => 'Chưa cấp',
            self::DaCap => 'Đã cấp',
            self::CapLoi => 'Cấp lỗi',
        };
    }
}
