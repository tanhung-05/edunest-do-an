<?php

namespace App\Enums\M21;

/** Điều kiện trao huy hiệu (BR-XP-43..46, A1a). */
enum BadgeRule: string
{
    case KhoaHoanThanh = 'KHOA_HOAN_THANH';
    case ChuoiNgay = 'CHUOI_NGAY';
    case TopKyThi = 'TOP_KY_THI';
    case CauDung = 'CAU_DUNG';

    public function label(): string
    {
        return match ($this) {
            self::KhoaHoanThanh => 'Số khóa đã hoàn thành ≥ ngưỡng',
            self::ChuoiNgay => 'Chuỗi ngày đạt ngưỡng',
            self::TopKyThi => 'Hạng chính thức kỳ thi ≤ ngưỡng',
            self::CauDung => 'Tổng câu đúng ≥ ngưỡng',
        };
    }
}
