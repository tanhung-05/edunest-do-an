<?php

use App\Support\M28\VietQr;

it('CR-261: CRC16 đúng chuẩn CCITT-FALSE', function () {
    expect(VietQr::crc('123456789'))->toBe('29B1');
});

it('CR-261: chuỗi VietQR có đủ trường EMVCo và mã kiểm tra khớp', function () {
    $qr = VietQr::payload('970436', '0011001234567', 499000, 'DH-ABC123');
    expect($qr)->toStartWith('000201010212')
        ->toContain('0010A000000727')
        ->toContain('0006970436')
        ->toContain('01130011001234567')
        ->toContain('0208QRIBFTTA')
        ->toContain('5303704')
        ->toContain('5406499000')
        ->toContain('5802VN')
        ->toContain('62130809DH-ABC123')
        ->and(substr($qr, -8, 4))->toBe('6304')
        ->and(substr($qr, -4))->toBe(VietQr::crc(substr($qr, 0, -4)));
});

it('CR-261: nội dung có dấu được bỏ dấu', function () {
    expect(VietQr::payload('970436', '123456', 1000, 'Học phí'))->toContain('0807Hoc phi');
});
