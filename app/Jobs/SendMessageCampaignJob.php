<?php

namespace App\Jobs;

use App\Models\MessageDelivery;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMessageCampaignJob implements ShouldQueue
{
    use ConfiguresTeamMail, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        try
        {
            Log::info('🚀 SendMessageCampaignJob: Starting job execution', [
                'delivery_id' => $this->messageDelivery->id,
                'job_queue' => $this->queue ?? 'default',
                'job_attempts' => $this->attempts(),
            ]);

            $this->messageDelivery->load(['contact', 'message', 'message.template', 'team']);

            // Check if it's time to send (respect scheduled time)
            if ($this->messageDelivery->sent_at && $this->messageDelivery->sent_at->isFuture())
            {
                Log::info('⏰ Message delivery not yet time to send, releasing job', [
                    'delivery_id' => $this->messageDelivery->id,
                    'scheduled_time' => $this->messageDelivery->sent_at,
                    'current_time' => now(),
                ]);

                $delay = $this->messageDelivery->sent_at->diffInSeconds(now());
                $this->release($delay);

                return;
            }

            // Validation checks
            if (! $this->validateDelivery())
            {
                return;
            }

            // Mark as sending
            $this->messageDelivery->update(['status_id' => 2]); // 2 = sending

            // Determine email provider
            $this->sendEmail();

            Log::info('✅ Message delivery sent successfully', [
                'delivery_id' => $this->messageDelivery->id,
                'contact_email' => $this->messageDelivery->contact->email,
            ]);
        } catch (\Exception $e)
        {
            $this->handleError($e);
            throw $e;
        }
    }

    /**
     * Validate the delivery before sending
     */
    private function validateDelivery(): bool
    {
        // Check if contact exists and has email
        if (! $this->messageDelivery->contact || ! $this->messageDelivery->contact->email)
        {
            Log::warning('Message delivery skipped: No contact or email', [
                'delivery_id' => $this->messageDelivery->id,
            ]);
            $this->messageDelivery->markAsError('No contact or email address available');

            return false;
        }

        // Check if message is still active
        if (! $this->messageDelivery->message || $this->messageDelivery->message->status_id != 1)
        {
            Log::info('Message delivery cancelled: Message not active', [
                'delivery_id' => $this->messageDelivery->id,
            ]);

            return false;
        }

        // Check if already delivered
        if ($this->messageDelivery->delivered_at)
        {
            Log::info('Message delivery already sent, skipping', [
                'delivery_id' => $this->messageDelivery->id,
            ]);

            return false;
        }

        // Validate email format
        if (! filter_var($this->messageDelivery->contact->email, FILTER_VALIDATE_EMAIL))
        {
            Log::warning('Message delivery skipped: Invalid email address', [
                'delivery_id' => $this->messageDelivery->id,
                'email' => $this->messageDelivery->contact->email,
            ]);
            $this->messageDelivery->markAsError('Invalid email address');

            return false;
        }

        return true;
    }

    /**
     * Send email using configured provider
     */
    private function sendEmail()
    {
        $apiEnabled = config('humano-mailer.providers.api.enabled', false);
        $fallbackToSmtp = config('humano-mailer.fallback_to_smtp', true);

        if ($apiEnabled)
        {
            try
            {
                $this->sendViaApi();

                return;
            } catch (\Exception $e)
            {
                Log::warning('API provider failed, falling back to SMTP', [
                    'delivery_id' => $this->messageDelivery->id,
                    'error' => $e->getMessage(),
                ]);

                if ($fallbackToSmtp)
                {
                    $this->sendViaSmtp();

                    return;
                }
                throw $e;
            }
        }

        $this->sendViaSmtp();
    }

    /**
     * Send email via generic API
     */
    private function sendViaApi()
    {
        Log::info('📧 SendMessageCampaignJob: Using API provider', [
            'delivery_id' => $this->messageDelivery->id,
        ]);

        $apiKey = config('humano-mailer.providers.api.key');
        $apiDomain = config('humano-mailer.providers.api.domain');

        if (! $apiKey)
        {
            throw new \Exception('API key not configured');
        }

        // Get email content
        $htmlContent = $this->messageDelivery->getHtmlForContact();

        // This is a generic implementation - you would adapt this for your specific API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.your-email-provider.com/send', [
            'from' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'to' => $this->messageDelivery->contact->email,
            'subject' => $this->messageDelivery->message->name,
            'html' => $htmlContent,
            'track_opens' => true,
            'track_clicks' => true,
        ]);

        if (! $response->successful())
        {
            throw new \Exception('API request failed: '.$response->body());
        }

        $result = $response->json();

        Log::info('✅ Email sent via API', [
            'delivery_id' => $this->messageDelivery->id,
            'provider_message_id' => $result['id'] ?? null,
        ]);

        // Mark as sent and delivered
        $this->messageDelivery->update([
            'email_provider' => 'api',
            'provider_message_id' => $result['id'] ?? null,
            'sent_at' => now(),
            'delivered_at' => now(),
            'delivery_status' => 'delivered',
            'status_id' => 3, // delivered
        ]);
    }

    /**
     * Send email via SMTP
     */
    private function sendViaSmtp()
    {
        Log::info('📧 SendMessageCampaignJob: Using SMTP', [
            'delivery_id' => $this->messageDelivery->id,
            'team_id' => $this->messageDelivery->team_id,
            'team_name' => $this->messageDelivery->team->name ?? 'Unknown',
        ]);

        // Configure mail settings for this team (uses ConfiguresTeamMail trait)
        $this->configureMailForTeam($this->messageDelivery->team);

        // Create mailable class name - this should be configurable
        $mailableClass = config('humano-mailer.mailables.message_delivery_mail', \App\Mail\MessageDeliveryMail::class);

        if (! class_exists($mailableClass))
        {
            throw new \Exception("Mailable class {$mailableClass} not found");
        }

        Mail::to($this->messageDelivery->contact->email)
            ->send(new $mailableClass($this->messageDelivery));

        Log::info('✅ Email sent via SMTP', [
            'delivery_id' => $this->messageDelivery->id,
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ]);

        // Mark as sent and delivered
        $this->messageDelivery->update([
            'email_provider' => 'smtp',
            'sent_at' => now(),
            'delivered_at' => now(),
            'delivery_status' => 'delivered',
            'status_id' => 3, // delivered
        ]);
    }

    /**
     * Handle job errors
     */
    private function handleError(\Exception $e)
    {
        $errorMessage = $e->getMessage();

        Log::error('❌ SendMessageCampaignJob: Failed to send message delivery', [
            'delivery_id' => $this->messageDelivery->id,
            'error_message' => $errorMessage,
            'error_code' => $e->getCode(),
        ]);

        $this->messageDelivery->markAsError($errorMessage);
    }

    /**
     * The job failed to process.
     */
    public function failed(\Exception $exception)
    {
        Log::error('Message delivery job failed permanently', [
            'delivery_id' => $this->messageDelivery->id,
            'error' => $exception->getMessage(),
        ]);

        $this->messageDelivery->markAsError($exception->getMessage());
    }
}
