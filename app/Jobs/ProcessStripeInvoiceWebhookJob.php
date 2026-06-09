<?php

namespace App\Jobs;

use App\Services\Billing\StripeInvoiceWebhookSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStripeInvoiceWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  array<string, mixed>  $invoicePayload
     */
    public function __construct(
        public array $invoicePayload,
        public string $eventType,
    ) {}

    public function handle(StripeInvoiceWebhookSyncService $syncService): void
    {
        $externalId = (string) ($this->invoicePayload['id'] ?? 'unknown');

        if (! $syncService->syncFromPayload($this->invoicePayload))
        {
            Log::warning('Stripe invoice webhook sync did not import core invoice', [
                'event' => $this->eventType,
                'external_id' => $externalId,
            ]);
        }
    }
}
