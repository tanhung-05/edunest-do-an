<?php

namespace App\Enums\M04;

/** 02 L20 loại câu (L chỉ nhận 3 loại đầu). */
enum QuestionType: string
{
    case MotDapAn = 'MOT_DAP_AN';
    case NhieuDapAn = 'NHIEU_DAP_AN';
    case DungSai = 'DUNG_SAI';
    case DienTu = 'DIEN_TU';
    case SapXep = 'SAP_XEP';
    case NoiCap = 'NOI_CAP';
    case DienNhieuCho = 'DIEN_NHIEU_CHO';

    public function label(): string
    {
        return match ($this) {
            self::MotDapAn => 'Một đáp án',
            self::NhieuDapAn => 'Nhiều đáp án',
            self::DungSai => 'Đúng/Sai',
            self::DienTu => 'Điền từ',
            self::SapXep => 'Sắp xếp',
            self::NoiCap => 'Nối cặp',
            self::DienNhieuCho => 'Điền nhiều chỗ',
        };
    }
}
