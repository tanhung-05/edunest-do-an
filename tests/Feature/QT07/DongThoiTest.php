<?php

use App\Actions\M07\RecoverOrderProcessing;
use App\Enums\M07\OrderStatus as DH;
use App\Enums\M07\PaymentEventOutcome as XN;
use App\Enums\M07\PaymentReconcileStatus as DS;
use App\Models\M07\Order;
use App\Models\M07\PaymentEvent;
use App\Models\M08\Enrollment;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\MuaHang as MH;

pest()->group('P3', 'concurrency', 'queue-db');

beforeEach(function () {
    $this->d = MH::d7();
    $this->a = $this->d['a'];
    $this->k = $this->d['k1'];
    MH::luc('10:00:00');
});

afterEach(fn () => test()->travelBack());

/**
 * Bộ chạy riêng cho P3 trong phạm vi tệp được phép. runner dùng chung hiện chỉ nhận kịch bản M01/M02.
 */
function giaoDichP3(array $a, string $point, array $b, bool $blocks = true): array
{
    expect(DB::connection()->getDatabaseName())->toStartWith('edunest_test');
    // RefreshDatabase sẽ nạp lại DB test ở ca kế tiếp vì giao dịch của fixture đã commit.
    DB::commit();
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'edunest-p3-'.bin2hex(random_bytes(8));
    mkdir($dir);
    $env = [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => DB::connection()->getDatabaseName(), 'DB_URL' => '',
        'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        'EDUNEST_BARRIER_DIR' => $dir,
    ];
    $runner = __DIR__.'/DongThoiRunner.php';
    $start = static function (array $scenario, string $side, string $barrier) use ($env, $runner): Process {
        $params = $scenario[1] + ['at' => now()->toISOString(), 'side' => $side];
        $process = new Process([PHP_BINARY, $runner, $scenario[0], json_encode($params, JSON_THROW_ON_ERROR)],
            base_path(), $env + ['EDUNEST_BARRIER_POINT' => $barrier], null, 30);
        $process->start();

        return $process;
    };
    $waitFor = static function (Process $process, string $path): void {
        $timeout = microtime(true) + 12;
        while (! file_exists($path)) {
            if (! $process->isRunning() || microtime(true) > $timeout) {
                throw new RuntimeException('Tiến trình chưa tới điểm dừng: '.$process->getErrorOutput().$process->getOutput());
            }
            usleep(20000);
        }
    };
    $result = static function (Process $process): array {
        $process->wait();
        if (! $process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput().$process->getOutput());
        }

        return json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
    };

    $first = $second = null;
    try {
        $first = $start($a, 'a', $point);
        $waitFor($first, $dir.DIRECTORY_SEPARATOR.$point.'.reached');
        $beforeLock = isset($b[1]['advance']) ? 'reconcile-payment-received' : 'khong-dung';
        $second = $start($b, 'b', $beforeLock);
        $waitFor($second, $dir.DIRECTORY_SEPARATOR.'b.ready');
        if (isset($b[1]['advance'])) {
            $waitFor($second, $dir.DIRECTORY_SEPARATOR.$beforeLock.'.reached');
            touch($dir.DIRECTORY_SEPARATOR.$beforeLock.'.release');
        }
        if ($blocks) {
            usleep(250000);
            expect($second->isRunning())->toBeTrue();
        } else {
            $result($second);
        }
        if (isset($b[1]['advance'])) {
            file_put_contents($dir.DIRECTORY_SEPARATOR.'clock', $b[1]['advance']);
        }
        touch($dir.DIRECTORY_SEPARATOR.$point.'.release');

        return [$result($first), $result($second)];
    } finally {
        $first?->stop();
        $second?->stop();
        foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($dir);
    }
}

it('AC-07-16 DT-02 Hai xác nhận cùng mã chỉ ghi một kết quả và một việc cấp', function () {
    $order = MH::dat($this->a, $this->k);
    $payment = MH::khoiTao($this->a, $order);
    $confirm = ['confirm', ['body' => MH::payload($payment)]];
    [$first, $second] = giaoDichP3($confirm, 'reconcile-payment-locked', $confirm);
    expect($first['value'])->toBe(XN::DaGhi->value)->and($second['value'])->toBe(XN::TrungLap->value)
        ->and(PaymentEvent::query()->count())->toBe(2)->and(MH::soViec())->toBe(1);
    expect(MH::chayCap())->toBeTrue()->and($order->fresh()->status)->toBe(DH::HoanTat)
        ->and(Enrollment::query()->count())->toBe(1);
});

it('AC-07-55 DT-01 Hủy và xác nhận tuần tự theo cả hai thứ tự khóa', function (bool $cancelFirst) {
    $order = MH::dat($this->a, $this->k);
    $payment = MH::khoiTao($this->a, $order);
    $confirm = ['confirm', ['body' => MH::payload($payment)]];
    $cancel = ['cancel', ['id' => $order->id, 'actor' => $this->a->id]];
    if ($cancelFirst) {
        [$first, $second] = giaoDichP3($cancel, 'cancel-order-locked', $confirm);
        expect($first['value'])->toBe(DH::DaHuy->value)->and($second['value'])->toBe(XN::DaGhi->value);
        expect($order->fresh()->status)->toBe(DH::CanXuLy)->and($payment->fresh()->reconcile_status)->toBe(DS::CanHoan)
            ->and(MH::soViec())->toBe(0);
    } else {
        [$first, $second] = giaoDichP3($confirm, 'reconcile-payment-locked', $cancel);
        expect($first['value'])->toBe(XN::DaGhi->value)->and($second['status'])->toBe(422);
        expect($payment->fresh()->reconcile_status)->toBe(DS::HopLe)->and(MH::chayCap())->toBeTrue()
            ->and($order->fresh()->status)->toBe(DH::HoanTat);
    }
})->with([true, false]);

it('AC-07-65 Đối soát đọc lại trạng thái dưới khóa sau mốc hết hạn', function () {
    $order = MH::dat($this->a, $this->k);
    $payment = MH::khoiTao($this->a, $order);
    $before = $order->expires_at->subSecond();
    $after = $order->expires_at->addSecond();
    [, $second] = giaoDichP3(['hold', ['id' => $order->id, 'at' => $before->toISOString()]], 'hold-order',
        ['confirm', ['body' => MH::payload($payment), 'at' => $before->toISOString(), 'advance' => $after->toISOString()]]);
    expect($second['value'])->toBe(XN::DaGhi->value)->and($payment->fresh()->reconcile_status)->toBe(DS::CanHoan)
        ->and($payment->events()->sole()->received_at->equalTo($before))->toBeTrue()
        ->and($order->fresh()->status_before_cxl)->toBe(DH::HetHan)->and(MH::soViec())->toBe(0);
});

it('DT-03 Worker cũ không cấp hoặc ghi lỗi khi quét đã thay vé', function () {
    $order = MH::dat($this->a, $this->k);
    $payment = MH::khoiTao($this->a, $order);
    MH::xacNhan($payment);
    $old = $order->fresh()->grant_ticket;
    giaoDichP3(['grant', []], 'grant-order-claimed', ['recover', ['id' => $order->id, 'at' => now()->addSeconds(76)->toISOString()]], false);
    expect($order->fresh()->grant_ticket)->not->toBe($old)
        ->and(MH::dong($order)->grant_attempts)->toBe(1)->and(Enrollment::query()->count())->toBe(0)
        ->and(MH::soViec())->toBe(1);
    MH::luc('10:02:14');
    expect(MH::chayCap())->toBeFalse();
    MH::luc('10:02:15');
    expect(MH::chayCap())->toBeTrue()->and($order->fresh()->status)->toBe(DH::HoanTat)
        ->and(MH::dong($order)->grant_attempts)->toBe(2);
});

it('DT-04 Hai tab thật đặt cùng khóa chỉ ghi một đơn', function () {
    $place = ['place', ['actor' => $this->a->id, 'course' => $this->k->id]];
    [$first, $second] = giaoDichP3($place, 'place-order-locked', $place);
    expect($first['value'])->toBe($second['value'])
        ->and(Order::query()->count())->toBe(1);
});

it('DT-22 Quét và xác nhận đồng thời phục hồi đúng một hàng đợi', function () {
    $order = MH::dat($this->a, $this->k);
    $payment = MH::khoiTao($this->a, $order);
    MH::xacNhan($payment);
    DB::table('jobs')->delete();
    giaoDichP3(['recover', ['id' => $order->id]], 'recover-order-locked', ['confirm', ['body' => MH::payload($payment)]]);
    expect(MH::soViec())->toBe(1);
    app(RecoverOrderProcessing::class)->handle($order->id);
    expect(MH::soViec())->toBe(1);
});
