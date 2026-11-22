<?php

namespace App\Enums\M10;

enum CertificatePdfStatus: string
{
    case ChoTao = 'CHO_TAO';
    case DaTao = 'DA_TAO';
    case Loi = 'LOI';

    public function label(): string
    {
        return match ($this) {
            self::ChoTao => 'Chờ tạo',
            self::DaTao => 'Đã tạo',
            self::Loi => 'Lỗi',
        };
    }
}
