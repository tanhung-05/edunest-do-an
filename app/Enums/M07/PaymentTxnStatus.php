<?php

namespace App\Enums\M07;

/** 03 §7a giao dịch. */
enum PaymentTxnStatus: string
{
    case KhoiTao = 'KHOI_TAO';
    case ThanhCong = 'THANH_CONG';
    case ThatBai = 'THAT_BAI';

    public function label(): string
    {
        return match ($this) {
            self::KhoiTao => 'Khởi tạo',
            self::ThanhCong => 'Thành công',
            self::ThatBai => 'Thất bại',
        };
    }
}
