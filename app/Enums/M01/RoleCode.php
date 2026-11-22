<?php

namespace App\Enums\M01;

/** 04 §1 — mã vai (roles.code). */
enum RoleCode: string
{
    case HocVien = 'HOC_VIEN';
    case GiaoVien = 'GIAO_VIEN';
    case GiaoVu = 'GIAO_VU';
    case QuanTri = 'QUAN_TRI';
    case PhuHuynh = 'PHU_HUYNH';

    public function label(): string
    {
        return match ($this) {
            self::HocVien => 'Học viên',
            self::GiaoVien => 'Giáo viên',
            self::GiaoVu => 'Giáo vụ',
            self::QuanTri => 'Quản trị',
            self::PhuHuynh => 'Phụ huynh',
        };
    }

    /** Vai nhân sự cần 2FA để có hiệu lực (BR-HT-14, CR-109). */
    public function requiresTwoFactor(): bool
    {
        return $this === self::GiaoVu || $this === self::QuanTri;
    }

    /**
     * Vai Quản trị gán được ở P0 (Phụ huynh thuộc P8).
     *
     * @return list<self>
     */
    public static function assignableInP0(): array
    {
        return [self::HocVien, self::GiaoVien, self::GiaoVu, self::QuanTri];
    }
}
