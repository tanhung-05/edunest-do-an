<?php

namespace App\Enums\M20;

/** Lý do chốt phiên luyện tập/game. */
enum SessionEndReason: string
{
    case HoanThanh = 'HOAN_THANH';
    case HetMang = 'HET_MANG';
    case PhienMoi = 'PHIEN_MOI';
    case KhongHoatDong = 'KHONG_HOAT_DONG';
    case ThuHoi = 'THU_HOI';
    case KhoaTaiKhoan = 'KHOA_TAI_KHOAN';

    public function label(): string
    {
        return match ($this) {
            self::HoanThanh => 'Hoàn thành',
            self::HetMang => 'Hết mạng',
            self::PhienMoi => 'Bắt đầu phiên mới',
            self::KhongHoatDong => 'Quá 60 phút không hoạt động',
            self::ThuHoi => 'Thu hồi quyền học',
            self::KhoaTaiKhoan => 'Tài khoản bị khóa',
        };
    }
}
