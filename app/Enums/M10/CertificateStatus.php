<?php

namespace App\Enums\M10;

enum CertificateStatus: string
{
    case HieuLuc = 'HIEU_LUC';
    case DaThuHoi = 'DA_THU_HOI';

    public function label(): string
    {
        return match ($this) {
            self::HieuLuc => 'Hiệu lực',
            self::DaThuHoi => 'Đã thu hồi',
        };
    }
}
