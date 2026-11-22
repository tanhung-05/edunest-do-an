<?php

namespace App\Enums\M03;

/** 03 §4 trạng thái bán. */
enum SaleStatus: string
{
    case ChuaBan = 'CHUA_BAN';
    case DangBan = 'DANG_BAN';
    case NgungBan = 'NGUNG_BAN';

    public function label(): string
    {
        return match ($this) {
            self::ChuaBan => 'Chưa bán',
            self::DangBan => 'Đang bán',
            self::NgungBan => 'Ngừng bán',
        };
    }
}
