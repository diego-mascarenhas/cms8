<?php

namespace App\Jobs;

use App\Jobs\SimulateEmailDeliveryJob;
use App\Mail\MessageDeliveryMail;
use App\Models\MessageDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMessageCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The message delivery instance.
     *
     * @var \App\Models\MessageDelivery
     */
    public $messageDelivery;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job should run.
     *
     * @var int
     */
    public $timeout = 120;

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
     */
    public function handle()
    {
        try {
            $this->messageDelivery->load(['contact', 'message', 'message.template', 'team']);

            // Check if it's time to send (respect scheduled time)
            if ($this->messageDelivery->sent_at && $this->messageDelivery->sent_at->isFuture()) {
                Log::info('Message delivery not yet time to send, rescheduling', [
                    'delivery_id' => $this->messageDelivery->id,
                    'scheduled_time' => $this->messageDelivery->sent_at,
                    'current_time' => now(),
                ]);
                // Reschedule for the correct time
                $delay = $this->messageDelivery->sent_at->diffInSeconds(now());
                static::dispatch($this->messageDelivery)->delay($delay);
                return;
            }

            // Check if contact exists and has email
            if (!$this->messageDelivery->contact || !$this->messageDelivery->contact->email) {
                Log::warning('Message delivery skipped: No contact or email', [
                    'delivery_id' => $this->messageDelivery->id,
                    'contact_id' => $this->messageDelivery->contact_id
                ]);
                $this->messageDelivery->markAsError();
                return;
            }

            // Check if message is still active
            if (!$this->messageDelivery->message || $this->messageDelivery->message->status_id != 1) {
                Log::info('Message delivery cancelled: Message not active', [
                    'delivery_id' => $this->messageDelivery->id,
                    'message_id' => $this->messageDelivery->message_id
                ]);
                return;
            }

            // Check if already delivered
            if ($this->messageDelivery->delivered_at) {
                Log::info('Message delivery already sent, skipping', [
                    'delivery_id' => $this->messageDelivery->id,
                    'delivered_at' => $this->messageDelivery->delivered_at,
                ]);
                return;
            }

            // Validate contact email
            if (!filter_var($this->messageDelivery->contact->email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Message delivery skipped: Invalid email address', [
                    'delivery_id' => $this->messageDelivery->id,
                    'email' => $this->messageDelivery->contact->email,
                ]);
                $this->messageDelivery->markAsError();
                return;
            }

            // Mark as sending
            $this->messageDelivery->update(['status_id' => 3]); // 3 = sending

            // Send the email
            Mail::to($this->messageDelivery->contact->email)
                ->send(new MessageDeliveryMail($this->messageDelivery));

            // Mark as sent (NOT delivered yet - we need webhook confirmation for that)
            $this->messageDelivery->update([
                'sent_at' => now(), // Actual send time
                'status_id' => 2, // 2 = sent (not delivered)
            ]);

            // Schedule a job to simulate delivery confirmation (5-10 minutes later)
            // In production, this would be replaced by actual webhook handling
            $deliveryDelayMinutes = rand(5, 10);
            SimulateEmailDeliveryJob::dispatch($this->messageDelivery)
                ->delay(now()->addMinutes($deliveryDelayMinutes));

            Log::info('Message delivery sent successfully', [
                'delivery_id' => $this->messageDelivery->id,
                'contact_email' => $this->messageDelivery->contact->email,
                'message_id' => $this->messageDelivery->message_id,
                'scheduled_time' => $this->messageDelivery->sent_at,
                'actual_send_time' => now(),
                'simulated_delivery_in_minutes' => $deliveryDelayMinutes,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send message delivery', [
                'delivery_id' => $this->messageDelivery->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark as failed
            $this->messageDelivery->markAsError();

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * The job failed to process.
     *
     * @param \Exception $exception
     * @return void
     */
    public function failed(\Exception $exception)
    {
        Log::error('Message delivery job failed permanently', [
            'delivery_id' => $this->messageDelivery->id,
            'error' => $exception->getMessage()
        ]);

        // Mark as permanently failed
        $this->messageDelivery->markAsError();
    }
}
