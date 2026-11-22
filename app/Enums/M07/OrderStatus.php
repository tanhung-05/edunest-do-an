<?php

namespace App\Enums\M07;

/** 03 §6 trạng thái đơn. */
enum OrderStatus: string
{
    case ChoThanhToan = 'CHO_THANH_TOAN';
    case DangCapQuyen = 'DANG_CAP_QUYEN';
    case CanXuLy = 'CAN_XU_LY';
    case HoanTat = 'HOAN_TAT';
    case DaHuy = 'DA_HUY';
    case HetHan = 'HET_HAN';

    public function label(): string
    {
        return match ($this) {
            self::ChoThanhToan => 'Chờ thanh toán',
            self::DangCapQuyen => 'Đang cấp quyền',
            self::CanXuLy => 'Cần xử lý',
            self::HoanTat => 'Hoàn tất',
            self::DaHuy => 'Đã hủy',
            self::HetHan => 'Hết hạn',
        };
    }
}
