<?php

namespace App\Enums\M15;

/** M15: loại thông báo. Loại tiền/quyền học không tắt được (BR-HT-46, CR-51). */
enum NotificationKind: string
{
    case MuaThanhCong = 'MUA_THANH_CONG';
    case CapQuyenLoi = 'CAP_QUYEN_LOI';
    case SapHetHan = 'SAP_HET_HAN';
    case ChungNhan = 'CHUNG_NHAN';
    case KhuyenMai = 'KHUYEN_MAI';
    case GiaoSoan = 'GIAO_SOAN';
    case KetQuaDuyet = 'KET_QUA_DUYET';
    case PhienBanMoi = 'PHIEN_BAN_MOI';
    case CanhBaoBatThuong = 'CANH_BAO';
    case TraLoiHoiDap = 'TRA_LOI_HOI_DAP';
    case KyThi = 'KY_THI';
    case BuoiTrucTiep = 'BUOI_TRUC_TIEP';

    public function label(): string
    {
        return match ($this) {
            self::MuaThanhCong => 'Mua thành công',
            self::CapQuyenLoi => 'Cấp quyền lỗi',
            self::SapHetHan => 'Sắp hết hạn truy cập',
            self::ChungNhan => 'Nhận chứng nhận',
            self::KhuyenMai => 'Khóa yêu thích giảm giá',
            self::GiaoSoan => 'Được giao soạn khóa',
            self::KetQuaDuyet => 'Kết quả duyệt phiên bản',
            self::PhienBanMoi => 'Khóa có phiên bản mới',
            self::CanhBaoBatThuong => 'Cảnh báo đăng nhập bất thường',
            self::TraLoiHoiDap => 'Có trả lời hỏi đáp',
            self::KyThi => 'Kỳ thi thử (nhắc lịch, có kết quả)',
            self::BuoiTrucTiep => 'Nhắc buổi học trực tiếp',
        };
    }

    public function mutable(): bool
    {
        return ! in_array($this, [self::MuaThanhCong, self::CapQuyenLoi, self::SapHetHan], true);
    }

    /** BR-HT-45: sự kiện quan trọng có thêm email qua hàng đợi. */
    public function emailed(): bool
    {
        return in_array($this, [self::MuaThanhCong, self::CapQuyenLoi, self::SapHetHan, self::ChungNhan], true);
    }
}
