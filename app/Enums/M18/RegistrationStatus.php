<?php

namespace App\Enums\M18;

/** Đăng ký thi (02c DK-1/2, CR-145). */
enum RegistrationStatus: string
{
    case DaDangKy = 'DA_DANG_KY';
    case DaHuy = 'DA_HUY';

    public function label(): string
    {
        return match ($this) {
            self::DaDangKy => 'Đã đăng ký',
            self::DaHuy => 'Đã hủy',
        };
    }
}
