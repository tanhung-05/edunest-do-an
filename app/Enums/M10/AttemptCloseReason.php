<?php

namespace App\Enums\M10;

enum AttemptCloseReason: string
{
    case Nop = 'NOP';
    case HetGio = 'HET_GIO';
    case DongCuaSo = 'DONG_CUA_SO';
    case ThuHoi = 'THU_HOI';

    public function label(): string
    {
        return match ($this) {
            self::Nop => 'Nộp bài',
            self::HetGio => 'Hết giờ',
            self::DongCuaSo => 'Đóng cửa sổ',
            self::ThuHoi => 'Thu hồi',
        };
    }
}
