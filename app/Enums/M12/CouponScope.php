<?php

namespace App\Enums\M12;

/** Phạm vi mã giảm giá (BR-TT-57, CR-125). */
enum CouponScope: string
{
    case ToanBo = 'TOAN_BO';
    case DanhMuc = 'DANH_MUC';
    case Khoa = 'KHOA';

    public function label(): string
    {
        return match ($this) {
            self::ToanBo => 'Toàn bộ',
            self::DanhMuc => 'Danh mục',
            self::Khoa => 'Một khóa',
        };
    }
}
