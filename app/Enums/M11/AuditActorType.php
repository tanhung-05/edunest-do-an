<?php

namespace App\Enums\M11;

/** 02 L35 loại người làm. */
enum AuditActorType: string
{
    case TaiKhoan = 'TAI_KHOAN';
    case LenhMayChu = 'LENH_MAY_CHU';
    case HeThong = 'HE_THONG';

    public function label(): string
    {
        return match ($this) {
            self::TaiKhoan => 'Tài khoản',
            self::LenhMayChu => 'Lệnh máy chủ',
            self::HeThong => 'Hệ thống',
        };
    }
}
