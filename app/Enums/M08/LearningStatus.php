<?php

namespace App\Enums\M08;

/** 03 §9a trạng thái học. */
enum LearningStatus: string
{
    case DangHoc = 'DANG_HOC';
    case HoanThanh = 'HOAN_THANH';

    public function label(): string
    {
        return match ($this) {
            self::DangHoc => 'Đang học',
            self::HoanThanh => 'Hoàn thành',
        };
    }
}
