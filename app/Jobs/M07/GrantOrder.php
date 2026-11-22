<?php

namespace App\Jobs\M07;

use App\Actions\M07\ClaimGrantAttempt;
use App\Actions\M07\MarkOrderItemGrantFailed;
use App\Actions\M07\RecoverOrderProcessing;
use App\Actions\M08\GrantOrderItem;
use App\Models\M11\Setting;
use App\Support\M07\GrantRun;
use App\Support\TestBarrier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use RuntimeException;
use Throwable;

#[Tries(1)]
#[Timeout(GrantRun::TIMEOUT)]
final class GrantOrder implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $orderId, public readonly string $ticket)
    {
        $this->onConnection('database')->onQueue('grants');
    }

    public function handle(ClaimGrantAttempt $claim, GrantOrderItem $grant, MarkOrderItemGrantFailed $failed, RecoverOrderProcessing $recover): void
    {
        $jobId = (int) $this->job?->getJobId();
        $run = $claim->handle($this->orderId, $this->ticket, $jobId);
        if ($run === null) {
            return;
        }

        TestBarrier::reach('grant-order-claimed');
        $simulate = Setting::grantFailureMode();
        try {
            if ($simulate) {
                throw new RuntimeException('Lỗi cấp quyền mô phỏng.');
            }
            $saved = $grant->handle($run);
        } catch (Throwable $e) {
            if (! $simulate) {
                report($e);
            }
            $saved = $failed->handle($run, $simulate ? 'Lỗi cấp quyền mô phỏng.' : 'Tác vụ cấp quyền gặp lỗi kỹ thuật.');
        }

        if ($saved) {
            TestBarrier::reach('grant-order-saved');
            $recover->handle($this->orderId, $run);
        }
    }
}
