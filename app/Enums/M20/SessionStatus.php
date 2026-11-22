<?php

namespace App\Enums\M20;

/** Trạng thái phiên luyện tập và phiên game (BR-XP-06/14–16, CR-91). */
enum SessionStatus: string
{
    case DangLam = 'DANG_LAM';
    case HoanThanh = 'HOAN_THANH';
    case HetMang = 'HET_MANG';
    case BoDo = 'BO_DO';

    public function label(): string
    {
        return match ($this) {
            self::DangLam => 'Đang làm',
            self::HoanThanh => 'Hoàn thành',
            self::HetMang => 'Hết mạng',
            self::BoDo => 'Bỏ dở',
        };
    }
}
