<?php

namespace App\Enums\M22;

/** Loại mini game (M22). */
enum GameKind: string
{
    case BanBong = 'BAN_BONG';
    case MuaChu = 'MUA_CHU';

    public function label(): string
    {
        return match ($this) {
            self::BanBong => 'Bắn bong bóng',
            self::MuaChu => 'Mưa chữ',
        };
    }
}
