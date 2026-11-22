<?php

namespace App\Enums\M07;

/** Kênh thanh toán. */
enum PaymentChannel: string
{
    case MoPhong = 'MO_PHONG';
    case ChuyenKhoan = 'CHUYEN_KHOAN';

    public function label(): string
    {
        return match ($this) {
            self::MoPhong => 'Mô phỏng',
            self::ChuyenKhoan => 'Chuyển khoản',
        };
    }
}
