<?php

namespace App\Enums\M08;

/** 03 §9b hiệu lực (suy ra, không lưu). */
enum EnrollmentValidity: string
{
    case ConHan = 'CON_HAN';
    case HetHan = 'HET_HAN';
    case DaThuHoi = 'DA_THU_HOI';

    public function label(): string
    {
        return match ($this) {
            self::ConHan => 'Còn hạn',
            self::HetHan => 'Đã hết hạn truy cập',
            self::DaThuHoi => 'Đã thu hồi',
        };
    }
}
