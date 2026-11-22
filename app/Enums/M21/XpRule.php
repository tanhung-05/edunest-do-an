<?php

namespace App\Enums\M21;

/** Khoản trong sổ XP (BR-XP-20..42). */
enum XpRule: string
{
    case Cau = 'CAU';
    case ThuongPhien = 'THUONG_PHIEN';
    case ThuongGame = 'THUONG_GAME';
    case Bai = 'BAI';
    case Khoa = 'KHOA';
    case Chuoi3 = 'CHUOI_3';
    case Chuoi7 = 'CHUOI_7';
    case Chuoi14 = 'CHUOI_14';
    case Chuoi30 = 'CHUOI_30';

    public function label(): string
    {
        return match ($this) {
            self::Cau => 'Câu luyện tập',
            self::ThuongPhien => 'Thưởng hoàn thành phiên',
            self::ThuongGame => 'Thưởng mini game',
            self::Bai => 'Hoàn thành bài',
            self::Khoa => 'Hoàn thành khóa',
            self::Chuoi3 => 'Chuỗi 3 ngày',
            self::Chuoi7 => 'Chuỗi 7 ngày',
            self::Chuoi14 => 'Chuỗi 14 ngày',
            self::Chuoi30 => 'Chuỗi 30 ngày',
        };
    }
}
