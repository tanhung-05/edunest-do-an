<?php

namespace App\Support\M05;

use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * TV-07: đọc thời lượng MP4. Đọc trực tiếp hộp `moov/mvhd` bằng PHP nên không cần cài chương trình ngoài; tệp lạ
 * (không đọc được hộp) mới thử `ffprobe` nếu máy có. Test thay bằng bản giả qua container.
 */
class VideoProbe
{
    /** Thời lượng mili giây, hoặc null khi không đọc được. */
    public function durationMs(string $absolutePath): ?int
    {
        return self::mp4DurationMs($absolutePath) ?? $this->ffprobeDurationMs($absolutePath);
    }

    /** Đọc `mvhd` (ISO BMFF). `moov` có thể nằm sau `mdat` nên đi theo kích thước hộp, không đọc cả tệp. */
    public static function mp4DurationMs(string $absolutePath): ?int
    {
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return null;
        }
        try {
            $size = filesize($absolutePath);
            if ($size === false) {
                return null;
            }
            $moov = self::findBox($handle, 0, $size, 'moov');
            if ($moov === null) {
                return null;
            }
            $mvhd = self::findBox($handle, $moov[0], $moov[1], 'mvhd');
            if ($mvhd === null || $mvhd[1] - $mvhd[0] < 20) {
                return null;
            }
            fseek($handle, $mvhd[0]);
            $version = ord((string) fread($handle, 4));
            if ($version === 1) {
                fread($handle, 16);
                $head = (string) fread($handle, 12);
                if (strlen($head) < 12) {
                    return null;
                }
                $timescale = self::u32($head, 0);
                $duration = self::u64($head, 4);
            } else {
                fread($handle, 8);
                $head = (string) fread($handle, 8);
                if (strlen($head) < 8) {
                    return null;
                }
                $timescale = self::u32($head, 0);
                $duration = self::u32($head, 4);
            }
            if ($timescale === null || $duration === null || $timescale <= 0 || $duration <= 0) {
                return null;
            }
            $ms = (int) round($duration * 1000 / $timescale);

            return $ms > 0 ? $ms : null;
        } catch (Throwable) {
            return null;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Tìm hộp con theo loại trong đoạn [$start, $end). Trả [đầu nội dung, cuối hộp] hoặc null.
     *
     * @param  resource  $handle
     * @return array{0: int, 1: int}|null
     */
    private static function findBox($handle, int $start, int $end, string $type): ?array
    {
        $pos = $start;
        while ($pos + 8 <= $end) {
            fseek($handle, $pos);
            $header = (string) fread($handle, 8);
            if (strlen($header) < 8) {
                return null;
            }
            $boxSize = self::u32($header, 0);
            $boxType = substr($header, 4, 4);
            $headerSize = 8;
            if ($boxSize === 1) {
                $large = (string) fread($handle, 8);
                if (strlen($large) < 8) {
                    return null;
                }
                $boxSize = self::u64($large, 0);
                $headerSize = 16;
            } elseif ($boxSize === 0) {
                $boxSize = $end - $pos;
            }
            if ($boxSize === null || $boxSize < $headerSize || $pos + $boxSize > $end) {
                return null;
            }
            if ($boxType === $type) {
                return [$pos + $headerSize, $pos + $boxSize];
            }
            $pos += $boxSize;
        }

        return null;
    }

    private static function u32(string $bytes, int $offset): ?int
    {
        $value = unpack('N', $bytes, $offset);

        return is_array($value) && is_int($value[1] ?? null) ? $value[1] : null;
    }

    private static function u64(string $bytes, int $offset): ?int
    {
        $value = unpack('J', $bytes, $offset);

        return is_array($value) && is_int($value[1] ?? null) ? $value[1] : null;
    }

    private function ffprobeDurationMs(string $absolutePath): ?int
    {
        $bin = (string) config('services.ffprobe.path', 'ffprobe');
        try {
            $result = Process::timeout(60)->run([$bin, '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=nw=1:nk=1', $absolutePath]);
        } catch (Throwable) {
            return null;
        }
        if (! $result->successful()) {
            return null;
        }
        $seconds = (float) trim($result->output());

        return $seconds > 0 ? (int) round($seconds * 1000) : null;
    }
}
