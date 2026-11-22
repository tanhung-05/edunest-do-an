<?php

namespace App\Enums\M01;

/** 03 §2 — M01 trạng thái tài khoản (users.status). */
enum AccountStatus: string
{
    case HoatDong = 'HOAT_DONG';
    case BiKhoa = 'BI_KHOA';

    public function label(): string
    {
        return match ($this) {
            self::HoatDong => 'Đang hoạt động',
            self::BiKhoa => 'Bị khóa',
        };
    }
}
