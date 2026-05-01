<?php

namespace App\Jobs;

use App\Models\MessageDelivery;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        $fallback = config('message_delivery_dispatch.fallback_queue');
        if (is_string($fallback) && $fallback !== '')
        {
            $this->onQueue($fallback);
        }
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
            if ($this->messageDelivery->scheduled_for && $this->messageDelivery->scheduled_for->isFuture())
            {
                Log::info('⏰ Message delivery not yet time to send, releasing job', [
                    'delivery_id' => $this->messageDelivery->id,
                    'scheduled_time' => $this->messageDelivery->scheduled_for,
                    'current_time' => now(),
                ]);

                $delay = $this->messageDelivery->scheduled_for->diffInSeconds(now());
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
        $mailBabyEnabled = config('services.mailbaby.enabled', false);
        $fallbackToSmtp = config('services.email.fallback_to_smtp', true);

        Log::info('🔧 SendMessageCampaignJob: Email provider configuration', [
            'delivery_id' => $this->messageDelivery->id,
            'mailbaby_enabled' => $mailBabyEnabled,
            'fallback_to_smtp' => $fallbackToSmtp,
        ]);

        if ($mailBabyEnabled && config('services.mailbaby.api_key'))
        {
            try
            {
                $this->sendViaMailBabyApi();

                return;
            } catch (\Exception $e)
            {
                Log::warning('⚠️  MailBaby API failed, falling back to SMTP', [
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
     * Send email via MailBaby API
     */
    private function sendViaMailBabyApi()
    {
        Log::info('📧 SendMessageCampaignJob: Using MailBaby API', [
            'delivery_id' => $this->messageDelivery->id,
            'contact_email' => $this->messageDelivery->contact->email,
        ]);

        $mailBabyService = app(\App\Services\MailBabyService::class);

        // Get email content
        $htmlContent = $this->messageDelivery->getHtmlForContact();

        // Use team's configured email settings (already set by configureMailForTeam)
        $fromName = config('mail.from.name');
        $fromEmail = config('mail.from.address');

        // Prepare email data for MailBaby API
        $emailData = [
            'to' => $this->messageDelivery->contact->email,
            'from' => $fromName.' <'.$fromEmail.'>',
            'subject' => $this->messageDelivery->message->name,
            'body' => $htmlContent,
            'message_id' => $this->messageDelivery->id, // For logging purposes
        ];

        // Send via MailBaby API
        $result = $mailBabyService->sendEmail($emailData);

        if (! $result['success'])
        {
            throw new \Exception('MailBaby API request failed: '.($result['error'] ?? 'Unknown error'));
        }

        Log::info('✅ SendMessageCampaignJob: Email sent via MailBaby API', [
            'delivery_id' => $this->messageDelivery->id,
            'mailbaby_message_id' => $result['message_id'] ?? null,
            'contact_email' => $this->messageDelivery->contact->email,
            'from' => $fromEmail,
        ]);

        // Mark as sent (not delivered yet - wait for webhook)
        $this->messageDelivery->update([
            'email_provider' => 'mailbaby',
            'provider_message_id' => $result['message_id'] ?? null,
            'sent_at' => now(),
            'delivery_status' => 'sent',
            'status_id' => 2, // sent (waiting for delivery confirmation)
            'provider_data' => $result['data'] ?? null,
        ]);

        // Increment team email usage
        $this->messageDelivery->team->incrementEmailUsage();
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

        // IMPORTANT: Configure mail settings for this team BEFORE creating the Mailable
        $this->configureMailForTeam($this->messageDelivery->team);

        // Log the configuration that will be used
        Log::info('📧 Mail configuration after team setup', [
            'delivery_id' => $this->messageDelivery->id,
            'config_from_address' => config('mail.from.address'),
            'config_from_name' => config('mail.from.name'),
            'smtp_host' => config('mail.mailers.smtp.host'),
            'smtp_username' => config('mail.mailers.smtp.username'),
        ]);

        // Create mailable class name - this should be configurable
        $mailableClass = config('humano-mailer.mailables.message_delivery_mail', \App\Mail\MessageDeliveryMail::class);

        if (! class_exists($mailableClass))
        {
            throw new \Exception("Mailable class {$mailableClass} not found");
        }

        // Create the mailable AFTER configuring the team settings
        $mailable = new $mailableClass($this->messageDelivery);

        // Send the email
        Mail::to($this->messageDelivery->contact->email)->send($mailable);

        Log::info('✅ Email sent via SMTP', [
            'delivery_id' => $this->messageDelivery->id,
            'sent_to' => $this->messageDelivery->contact->email,
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
