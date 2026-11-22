<?php

namespace App\Enums\M04;

/** 03 §5 câu hỏi. */
enum QuestionStatus: string
{
    case Nhap = 'NHAP';
    case DangDung = 'DANG_DUNG';
    case NgungDung = 'NGUNG_DUNG';

    public function label(): string
    {
        return match ($this) {
            self::Nhap => 'Nháp',
            self::DangDung => 'Đang dùng',
            self::NgungDung => 'Ngừng dùng',
        };
    }
}
