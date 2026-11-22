<?php

use App\Actions\M07\CancelOrder;
use App\Actions\M07\ExpireOrder;
use App\Actions\M07\PlaceOrder;
use App\Actions\M07\ReconcilePayment;
use App\Actions\M07\RecoverOrderProcessing;
use App\Models\M03\Course;
use App\Models\M07\Order;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M07\PaymentConfirmation;
use App\Support\TestBarrier;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || ! str_starts_with(DB::connection()->getDatabaseName(), 'edunest_test')) {
    exit(2);
}

$args = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
$dir = getenv('EDUNEST_BARRIER_DIR');
if (isset($args['advance'])) {
    $clock = $dir.DIRECTORY_SEPARATOR.'clock';
    CarbonImmutable::setTestNow(static function () use ($clock, $args): CarbonImmutable {
        clearstatcache(true, $clock);

        return CarbonImmutable::parse(is_file($clock) ? file_get_contents($clock) : $args['at']);
    });
} else {
    CarbonImmutable::setTestNow($args['at']);
}
touch($dir.DIRECTORY_SEPARATOR.$args['side'].'.ready');

try {
    $result = match ($argv[1]) {
        'cancel' => app(CancelOrder::class)->handle(User::findOrFail($args['actor']), Order::findOrFail($args['id']))->status->value,
        'confirm' => app(ReconcilePayment::class)->handle($args['body'], PaymentConfirmation::sign($args['body']))->outcome->value,
        'recover' => app(RecoverOrderProcessing::class)->handle($args['id']),
        'expire' => app(ExpireOrder::class)->handle($args['id'])->status->value,
        'place' => app(PlaceOrder::class)->handle(User::findOrFail($args['actor']), Course::findOrFail($args['course']))->id,
        'grant' => Queue::connection('database')->pop('grants')?->fire(),
        'hold' => Tx::run(function () use ($args) {
            OrderLocks::order($args['id']);
            TestBarrier::reach('hold-order');
        }),
        default => throw new InvalidArgumentException('Kịch bản kiểm tra không hợp lệ.'),
    };
    echo json_encode(['ok' => true, 'value' => $result], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (DomainRejection $e) {
    echo json_encode(['ok' => false, 'status' => $e->status, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR).PHP_EOL;
}
