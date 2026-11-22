<?php

namespace App\Enums\M07;

/** Kết cục xử lý xác nhận. */
enum PaymentEventOutcome: string
{
    case DaGhi = 'DA_GHI';
    case TrungLap = 'TRUNG_LAP';
    case TuChoiChuKy = 'TU_CHOI_CHU_KY';
    case TuChoiMaLa = 'TU_CHOI_MA_LA';

    public function label(): string
    {
        return match ($this) {
            self::DaGhi => 'Đã ghi',
            self::TrungLap => 'Trùng lặp',
            self::TuChoiChuKy => 'Từ chối chữ ký',
            self::TuChoiMaLa => 'Từ chối mã lạ',
        };
    }
}
