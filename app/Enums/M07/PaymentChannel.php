<?php

namespace App\Enums\M07;

/** Kênh thanh toán. */
enum PaymentChannel: string
{
    case MoPhong = 'MO_PHONG';
    case ChuyenKhoan = 'CHUYEN_KHOAN';
    /** P10 (CR-260): ví MoMo môi trường thử. */
    case MoMo = 'MOMO';

    public function label(): string
    {
        return match ($this) {
            self::MoPhong => 'Mô phỏng',
            self::ChuyenKhoan => 'Chuyển khoản',
            self::MoMo => 'Ví MoMo',
        };
    }
}
