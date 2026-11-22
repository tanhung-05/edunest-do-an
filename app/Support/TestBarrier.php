<?php

namespace App\Support;

/**
 * Điểm dừng cho test đồng thời (07 §5). Chỉ hoạt động khi APP_ENV=testing và có biến
 * EDUNEST_BARRIER_DIR: tiến trình ghi tệp "<điểm>.reached" rồi chờ tệp "<điểm>.release".
 */
final class TestBarrier
{
    public static function reach(string $point): void
    {
        $dir = getenv('EDUNEST_BARRIER_DIR');
        $only = getenv('EDUNEST_BARRIER_POINT');
        if (! $dir || app()->environment() !== 'testing' || ($only && $only !== $point)) {
            return;
        }
        file_put_contents($dir.DIRECTORY_SEPARATOR.$point.'.reached', (string) getmypid());
        $deadline = microtime(true) + 20;
        while (! file_exists($dir.DIRECTORY_SEPARATOR.$point.'.release') && microtime(true) < $deadline) {
            usleep(20_000);
        }
    }
}
