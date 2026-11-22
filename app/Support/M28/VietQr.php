<?php

namespace App\Support\M28;

use Illuminate\Support\Str;

/**
 * P10 (CR-261): chuỗi QR chuyển khoản theo chuẩn VietQR (EMVCo, NAPAS 247), app ngân hàng nào cũng đọc được
 * số tài khoản, số tiền và nội dung. Không cần đăng ký dịch vụ; chỉ cần mã BIN ngân hàng và số tài khoản nhận.
 */
final class VietQr
{
    public static function payload(string $bin, string $account, int $amount, string $memo): string
    {
        $beneficiary = self::tlv('00', $bin).self::tlv('01', $account);
        $merchant = self::tlv('00', 'A000000727').self::tlv('01', $beneficiary).self::tlv('02', 'QRIBFTTA');
        $memo = self::ascii($memo);
        $body = self::tlv('00', '01')
            .self::tlv('01', '12')
            .self::tlv('38', $merchant)
            .self::tlv('53', '704')
            .self::tlv('54', (string) $amount)
            .self::tlv('58', 'VN')
            .($memo === '' ? '' : self::tlv('62', self::tlv('08', $memo)))
            .'6304';

        return $body.self::crc($body);
    }

    private static function tlv(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /** Nội dung chuyển chỉ giữ chữ không dấu, số, khoảng trắng, gạch ngang (tối đa 25 ký tự cho an toàn). */
    private static function ascii(string $text): string
    {
        $plain = (string) preg_replace('/[^A-Za-z0-9 -]/', '', Str::ascii($text, 'vi'));

        return substr(trim($plain), 0, 25);
    }

    /** CRC-16/CCITT-FALSE (đa thức 0x1021, khởi tạo 0xFFFF), viết hoa 4 ký tự. */
    public static function crc(string $data): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
