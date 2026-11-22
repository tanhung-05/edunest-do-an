<?php

namespace App\Console\Commands;

use App\Actions\M18\ExamClock;
use Illuminate\Console\Command;

/** TV-12 (02c §4): mở/đóng/công bố kỳ thi, chốt lượt quá hạn, nhắc 24 giờ. Chạy mỗi phút, idempotent. */
final class ExamSchedules extends Command
{
    protected $signature = 'edunest:exams';

    protected $description = 'Tác vụ nền kỳ thi thử: mở, đóng, công bố, nhắc lịch';

    public function handle(ExamClock $clock): int
    {
        $result = $clock->tick();
        $this->info(json_encode($result) ?: '');

        return self::SUCCESS;
    }
}
