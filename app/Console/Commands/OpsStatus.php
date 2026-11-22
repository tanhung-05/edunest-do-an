<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

final class OpsStatus extends Command
{
    protected $signature = 'edunest:ops-status';

    protected $description = 'Xem lịch, hàng đợi và dấu hiệu hoạt động gần nhất của worker';

    public function handle(): int
    {
        $this->line('Worker: '.(Cache::has('edunest:worker-heartbeat') ? 'đang chạy' : 'không chạy hoặc chưa có nhịp'));
        $queue = Queue::connection('database');
        foreach (['grants', 'default', 'media'] as $name) {
            $this->line($name.': '.$queue->size($name).' tác vụ');
        }

        return $this->call('schedule:list');
    }
}
