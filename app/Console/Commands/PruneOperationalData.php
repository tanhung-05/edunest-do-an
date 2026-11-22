<?php

namespace App\Console\Commands;

use App\Support\Tx;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneOperationalData extends Command
{
    protected $signature = 'edunest:prune-operational-data';

    protected $description = 'Dọn tác vụ lỗi cũ và phiên đã hết hạn';

    public function handle(): int
    {
        return Tx::run(function () {
            $exit = $this->call('queue:prune-failed', ['--hours' => 720]);
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config()->string('session.table'))
                    ->where('last_activity', '<', now()->subMinutes(config()->integer('session.lifetime'))->timestamp)->delete();
            }

            return $exit;
        });
    }
}
