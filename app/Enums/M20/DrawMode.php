<?php

namespace App\Enums\M20;

/** Kiểu rút câu của hoạt động luyện tập (CR-153). */
enum DrawMode: string
{
    case NgauNhien = 'NGAU_NHIEN';
    case DiemYeu = 'DIEM_YEU';

    public function label(): string
    {
        return match ($this) {
            self::NgauNhien => 'Ngẫu nhiên',
            self::DiemYeu => 'Ưu tiên điểm yếu',
        };
    }
}
