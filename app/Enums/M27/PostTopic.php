<?php

namespace App\Enums\M27;

/** BR-DG-24, CR-244: chủ đề bài cộng đồng. */
enum PostTopic: string
{
    case HocTap = 'HOC_TAP';
    case OnThi = 'ON_THI';
    case HoiDap = 'HOI_DAP';
    case GiaoLuu = 'GIAO_LUU';

    public function label(): string
    {
        return match ($this) {
            self::HocTap => 'Học tập',
            self::OnThi => 'Ôn thi',
            self::HoiDap => 'Hỏi đáp chung',
            self::GiaoLuu => 'Giao lưu',
        };
    }
}
