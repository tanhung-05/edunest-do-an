<?php

namespace App\Console\Commands;

use App\Actions\M10\FinalizeAttempt;
use App\Enums\M10\AttemptStatus;
use App\Models\M10\QuizAttempt;
use Illuminate\Console\Command;

final class FinalizeOverdueAttempts extends Command
{
    protected $signature = 'learning:finalize-overdue-attempts';

    protected $description = 'Chốt các lượt quiz bài học đã quá hạn';

    public function handle(FinalizeAttempt $finalize): int
    {
        $count = 0;
        $attempts = QuizAttempt::query()->whereNotNull('enrollment_id')->where('status', AttemptStatus::DangLam)
            ->where('deadline_at', '<', now()->floorMillisecond()->format('Y-m-d H:i:s.v'));
        foreach ($attempts->lazyById() as $attempt) {
            $finalize->handle($attempt);
            $count++;
        }

        $this->info('Đã xét '.$count.' lượt quiz.');

        return self::SUCCESS;
    }
}
