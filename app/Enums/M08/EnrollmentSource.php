<?php

namespace App\Enums\M08;

/** Nguồn ghi danh. */
enum EnrollmentSource: string
{
    case DonHang = 'DON_HANG';
    case MaKichHoat = 'MA_KICH_HOAT';

    public function label(): string
    {
        return match ($this) {
            self::DonHang => 'Đơn hàng',
            self::MaKichHoat => 'Mã kích hoạt',
        };
    }
}
