<?php

use App\Support\M05\VideoProbe;

function vpBox(string $type, string $body): string
{
    return pack('N', 8 + strlen($body)).$type.$body;
}

function vpFile(string $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'vp');
    file_put_contents($path, $bytes);

    return $path;
}

it('đọc thời lượng mvhd phiên bản 0 khi moov nằm sau mdat', function () {
    $mvhd = vpBox('mvhd', "\0\0\0\0".pack('NNNN', 0, 0, 1000, 125_500).str_repeat("\0", 80));
    $path = vpFile(vpBox('ftyp', 'isom').vpBox('mdat', str_repeat('x', 4096)).vpBox('moov', $mvhd));

    expect(VideoProbe::mp4DurationMs($path))->toBe(125_500);
});

it('đọc mvhd phiên bản 1 và hộp kích thước 64 bit', function () {
    $mvhd = vpBox('mvhd', "\1\0\0\0".pack('JJNJ', 0, 0, 90_000, 90_000 * 61).str_repeat("\0", 80));
    $mdatBody = str_repeat('y', 100);
    $largeMdat = pack('N', 1).'mdat'.pack('J', 16 + strlen($mdatBody)).$mdatBody;
    $path = vpFile(vpBox('ftyp', 'isom').$largeMdat.vpBox('moov', $mvhd));

    expect(VideoProbe::mp4DurationMs($path))->toBe(61_000);
});

it('trả null với tệp không phải MP4 hoặc hộp hỏng', function () {
    expect(VideoProbe::mp4DurationMs(vpFile('không phải video')))->toBeNull()
        ->and(VideoProbe::mp4DurationMs(vpFile(pack('N', 999).'moov')))->toBeNull()
        ->and(VideoProbe::mp4DurationMs(sys_get_temp_dir().'/khong-ton-tai.mp4'))->toBeNull();
});
