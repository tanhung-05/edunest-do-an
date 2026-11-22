<?php

namespace App\Console\Commands;

use App\Actions\M23\GuardianReports;
use Illuminate\Console\Command;

/** QT-25 A3: báo cáo tuần cho Phụ huynh, lịch 08:00 Chủ nhật giờ Việt Nam (CR-99, CR-234). */
final class GuardianWeeklyReports extends Command
{
    protected $signature = 'edunest:guardian-reports';

    protected $description = 'Gửi báo cáo tuần cho phụ huynh';

    public function handle(GuardianReports $reports): int
    {
        $this->info('Đã xếp '.$reports->sendWeek().' email báo cáo tuần.');

        return self::SUCCESS;
    }
}
