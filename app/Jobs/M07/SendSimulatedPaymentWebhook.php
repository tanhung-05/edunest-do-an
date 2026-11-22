<?php

namespace App\Jobs\M07;

use App\Support\M07\PaymentConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Http;

#[Tries(4)]
final class SendSimulatedPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $paymentId, public readonly string $payload)
    {
        $this->onConnection('database')->onQueue('default');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        Http::acceptJson()->withHeaders(['X-Payment-Signature' => PaymentConfirmation::sign($this->payload)])
            ->timeout(15)->withBody($this->payload, 'application/json')
            ->post(route('webhooks.payment-sim'))->throw();
    }
}
