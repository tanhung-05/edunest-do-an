<?php

namespace App\Enums\M18;

/** Kỳ thi thử (02c KT-1..6). */
enum ExamStatus: string
{
    case Nhap = 'NHAP';
    case SapMo = 'SAP_MO';
    case DangMo = 'DANG_MO';
    case DaDong = 'DA_DONG';
    case DaCongBo = 'DA_CONG_BO';

    public function label(): string
    {
        return match ($this) {
            self::Nhap => 'Nháp',
            self::SapMo => 'Sắp mở',
            self::DangMo => 'Đang mở',
            self::DaDong => 'Đã đóng',
            self::DaCongBo => 'Đã công bố',
        };
    }
}
