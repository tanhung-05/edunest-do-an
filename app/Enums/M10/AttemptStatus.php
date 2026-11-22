<?php

namespace App\Enums\M10;

enum AttemptStatus: string
{
    case DangLam = 'DANG_LAM';
    case DaNop = 'DA_NOP';
    case HetGio = 'HET_GIO';

    public function label(): string
    {
        return match ($this) {
            self::DangLam => 'Đang làm',
            self::DaNop => 'Đã nộp',
            self::HetGio => 'Hết giờ',
        };
    }
}
