<?php

namespace App\Enums\M18;

/** Đối tượng kỳ thi (M18). */
enum ExamAudience: string
{
    case CongKhai = 'CONG_KHAI';
    case Khoa = 'KHOA';

    public function label(): string
    {
        return match ($this) {
            self::CongKhai => 'Công khai',
            self::Khoa => 'Học viên khóa chỉ định',
        };
    }
}
