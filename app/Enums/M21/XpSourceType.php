<?php

namespace App\Enums\M21;

/** Loại nguồn XP (02d §2, BR-XP-73). */
enum XpSourceType: string
{
    case PhienLuyenTap = 'PHIEN_LUYEN_TAP';
    case PhienGame = 'PHIEN_GAME';
    case HoanThanhBai = 'HOAN_THANH_BAI';
    case HoanThanhKhoa = 'HOAN_THANH_KHOA';
    case KyThi = 'KY_THI';

    public function label(): string
    {
        return match ($this) {
            self::PhienLuyenTap => 'Phiên luyện tập',
            self::PhienGame => 'Phiên mini game',
            self::HoanThanhBai => 'Hoàn thành bài',
            self::HoanThanhKhoa => 'Hoàn thành khóa',
            self::KyThi => 'Kỳ thi thử',
        };
    }
}
