<?php

namespace App\Enums\M05;

/** 03 §3 phiên bản nội dung. */
enum CourseVersionStatus: string
{
    case Nhap = 'NHAP';
    case ChoDuyet = 'CHO_DUYET';
    case TraLai = 'TRA_LAI';
    case DaDuyet = 'DA_DUYET';
    case DaCongBo = 'DA_CONG_BO';
    case DaThayThe = 'DA_THAY_THE';

    public function label(): string
    {
        return match ($this) {
            self::Nhap => 'Nháp',
            self::ChoDuyet => 'Chờ duyệt',
            self::TraLai => 'Trả lại',
            self::DaDuyet => 'Đã duyệt',
            self::DaCongBo => 'Đã công bố',
            self::DaThayThe => 'Đã thay thế',
        };
    }
}
