<?php

namespace App\Enums\M17;

/** Câu hỏi hỏi đáp (02c HD-1..4, CR-140). */
enum DiscussionStatus: string
{
    case ChuaTraLoi = 'CHUA_TRA_LOI';
    case DaTraLoi = 'DA_TRA_LOI';
    case DaChapNhan = 'DA_CHAP_NHAN';
    case DaAn = 'DA_AN';

    public function label(): string
    {
        return match ($this) {
            self::ChuaTraLoi => 'Chưa trả lời',
            self::DaTraLoi => 'Đã trả lời',
            self::DaChapNhan => 'Đã chấp nhận',
            self::DaAn => 'Đã ẩn',
        };
    }
}
