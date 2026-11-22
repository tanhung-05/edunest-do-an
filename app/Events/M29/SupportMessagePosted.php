<?php

namespace App\Events\M29;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * P10 M29: phát tin nhắn mới tới kênh của cuộc trò chuyện (chủ tài khoản và nhân sự hỗ trợ) và kênh hộp thư chung
 * của nhân sự. Phát sau khi giao dịch ghi tin đã chốt.
 */
final class SupportMessagePosted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array{id: int, from_staff: bool, sender: string, body: string, at: string}  $message
     */
    public function __construct(public readonly int $threadId, public readonly array $message, public readonly string $status) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('support.thread.'.$this->threadId), new PrivateChannel('support.staff')];
    }

    public function broadcastAs(): string
    {
        return 'support.message';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['thread_id' => $this->threadId, 'status' => $this->status, 'message' => $this->message];
    }
}
