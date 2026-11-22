<?php

namespace App\Enums\M07;

/** Trạng thái tệp hóa đơn (02b N10). */
enum InvoicePdfStatus: string
{
    case DangTao = 'DANG_TAO';
    case SanSang = 'SAN_SANG';
    case Loi = 'LOI';

    public function label(): string
    {
        return match ($this) {
            self::DangTao => 'Đang tạo',
            self::SanSang => 'Sẵn sàng',
            self::Loi => 'Lỗi',
        };
    }
}
