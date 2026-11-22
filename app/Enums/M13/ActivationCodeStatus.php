<?php

namespace App\Enums\M13;

/** Mã kích hoạt (03 §13, 02b MK-1..4). */
enum ActivationCodeStatus: string
{
    case ChuaDung = 'CHUA_DUNG';
    case DaDung = 'DA_DUNG';
    case HetHan = 'HET_HAN';
    case DaVoHieu = 'DA_VO_HIEU';

    public function label(): string
    {
        return match ($this) {
            self::ChuaDung => 'Chưa dùng',
            self::DaDung => 'Đã dùng',
            self::HetHan => 'Hết hạn',
            self::DaVoHieu => 'Đã vô hiệu',
        };
    }
}
