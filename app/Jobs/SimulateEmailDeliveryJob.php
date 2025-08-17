<?php

namespace App\Jobs;

use App\Models\MessageDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SimulateEmailDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $messageDelivery;

    /**
     * Create a new job instance.
     */
    public function __construct(MessageDelivery $messageDelivery)
    {
        $this->messageDelivery = $messageDelivery;
        $this->onQueue('mailer');
    }

    /**
     * Execute the job.
     * Simulates email delivery confirmation (would normally come from webhook)
     */
    public function handle(): void
    {
        try {
            // Check if email was actually sent and not already delivered
            if (!$this->messageDelivery->sent_at || $this->messageDelivery->delivered_at) {
                Log::info('SimulateEmailDeliveryJob: Skipping - not sent or already delivered', [
                    'delivery_id' => $this->messageDelivery->id,
                    'sent_at' => $this->messageDelivery->sent_at,
                    'delivered_at' => $this->messageDelivery->delivered_at,
                ]);
                return;
            }

            // Simulate delivery confirmation
            $this->messageDelivery->update([
                'delivered_at' => now(),
                'status_id' => 3, // 3 = delivered
            ]);

            Log::info('SimulateEmailDeliveryJob: Email delivery simulated', [
                'delivery_id' => $this->messageDelivery->id,
                'contact_email' => $this->messageDelivery->contact->email ?? 'unknown',
                'sent_at' => $this->messageDelivery->sent_at,
                'delivered_at' => $this->messageDelivery->delivered_at,
            ]);

        } catch (\Exception $e) {
            Log::error('SimulateEmailDeliveryJob: Error simulating delivery', [
                'delivery_id' => $this->messageDelivery->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
