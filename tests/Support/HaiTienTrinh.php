<?php

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Ép thứ tự hai giao dịch (07 §5): A chạy tới điểm dừng sau khi giữ khóa, B khởi chạy và chờ khóa,
 * rồi thả A. Trả kết quả JSON của cả hai.
 */
final class HaiTienTrinh
{
    /**
     * @param  array{0: string, 1: array<string, mixed>}  $a
     * @param  array{0: string, 1: array<string, mixed>}  $b
     * @return array{a: array<string, mixed>, b: array<string, mixed>}
     */
    public static function chay(array $a, string $diemDungA, array $b): array
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'edunest-barrier-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => (string) config('database.connections.mysql.database'), 'DB_URL' => '',
            'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ];
        $runner = base_path('tests/Support/runner.php');

        $pa = new Process([PHP_BINARY, $runner, $a[0], json_encode($a[1])], base_path(),
            $env + ['EDUNEST_BARRIER_DIR' => $dir, 'EDUNEST_BARRIER_POINT' => $diemDungA], null, 60);
        $pa->start();
        self::cho(fn () => file_exists($dir.DIRECTORY_SEPARATOR.$diemDungA.'.reached'), $pa, 'A chưa tới điểm dừng');

        $pb = new Process([PHP_BINARY, $runner, $b[0], json_encode($b[1])], base_path(), $env, null, 60);
        $pb->start();
        // B đã khởi động và đang chờ khóa do A giữ (khởi động Laravel mất vài trăm ms).
        usleep(1_500_000);
        if (! $pb->isRunning()) {
            throw new RuntimeException('B kết thúc trước khi A nhả khóa: '.$pb->getOutput().$pb->getErrorOutput());
        }
        touch($dir.DIRECTORY_SEPARATOR.$diemDungA.'.release');

        $pa->wait();
        $pb->wait();
        array_map('unlink', glob($dir.DIRECTORY_SEPARATOR.'*') ?: []);
        rmdir($dir);

        return ['a' => self::ketQua($pa), 'b' => self::ketQua($pb)];
    }

    private static function cho(callable $dk, Process $p, string $loi): void
    {
        $han = microtime(true) + 30;
        while (! $dk()) {
            if (! $p->isRunning() || microtime(true) > $han) {
                throw new RuntimeException($loi.': '.$p->getOutput().$p->getErrorOutput());
            }
            usleep(20_000);
        }
    }

    /** @return array<string, mixed> */
    private static function ketQua(Process $p): array
    {
        $lines = array_values(array_filter(explode("\n", trim($p->getOutput()))));
        $json = json_decode((string) end($lines), true);
        if (! is_array($json)) {
            throw new RuntimeException('Kết quả lạ: '.$p->getOutput().$p->getErrorOutput());
        }

        return $json;
    }
}
