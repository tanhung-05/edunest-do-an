<?php

namespace App\Enums\M12;

/** Chỗ/lượt dùng mã (02b N02, CR-120). */
enum CouponSlotStatus: string
{
    case GiuCho = 'GIU_CHO';
    case DaDung = 'DA_DUNG';
    case DaTra = 'DA_TRA';

    public function label(): string
    {
        return match ($this) {
            self::GiuCho => 'Đang giữ chỗ',
            self::DaDung => 'Đã dùng',
            self::DaTra => 'Đã trả',
        };
    }
}
