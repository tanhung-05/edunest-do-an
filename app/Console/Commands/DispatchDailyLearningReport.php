<?php

namespace App\Console\Commands;

use App\Jobs\M12\AggregateDailyReportMetrics;
use Illuminate\Console\Command;

final class DispatchDailyLearningReport extends Command
{
    protected $signature = 'learning:aggregate-daily-report';

    protected $description = 'Xếp tác vụ tổng hợp báo cáo của kỳ 02:00 giờ Việt Nam';

    public function handle(): int
    {
        dispatch(AggregateDailyReportMetrics::forDay(now())->onQueue('default'));

        return self::SUCCESS;
    }
}
