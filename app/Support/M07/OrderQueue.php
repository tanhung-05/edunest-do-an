<?php

namespace App\Support\M07;

use Illuminate\Contracts\Queue\Queue as QueueConnection;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use LogicException;

final class OrderQueue
{
    public static function connection(): QueueConnection
    {
        $queue = Queue::connection('database');
        if (! $queue instanceof DatabaseQueue || $queue->getDatabase() !== DB::connection()) {
            throw new LogicException('Hàng đợi thanh toán và cấp quyền phải dùng cùng kết nối cơ sở dữ liệu với đơn.');
        }

        return $queue;
    }
}
