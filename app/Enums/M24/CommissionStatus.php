<?php

namespace App\Enums\M24;

/** QT-26 bước 16–18, A6: hoa hồng chờ duyệt, đã chi (mô phỏng), hủy do hoàn khoản. */
enum CommissionStatus: string
{
    case ChoDuyet = 'CHO_DUYET';
    case DaChi = 'DA_CHI';
    case Huy = 'HUY';

    public function label(): string
    {
        return match ($this) {
            self::ChoDuyet => 'Chờ duyệt',
            self::DaChi => 'Đã chi (mô phỏng)',
            self::Huy => 'Đã hủy',
        };
    }
}
