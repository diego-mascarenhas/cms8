<?php

namespace App\Jobs;

use App\Mail\MailBabyMail;
use App\Mail\MessageDeliveryMail;
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
        $this->onQueue('mailer');
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            Log::info('🚀 SendMessageCampaignJob: Starting job execution', [
                'delivery_id' => $this->messageDelivery->id,
                'job_queue' => $this->queue ?? 'default',
                'job_attempts' => $this->attempts(),
            ]);

            $this->messageDelivery->load(['contact', 'message', 'message.template', 'team']);

            Log::info('📊 SendMessageCampaignJob: Data loaded', [
                'delivery_id' => $this->messageDelivery->id,
                'team_id' => $this->messageDelivery->team->id ?? 'null',
                'team_name' => $this->messageDelivery->team->name ?? 'null',
                'contact_email' => $this->messageDelivery->contact->email ?? 'null',
                'message_id' => $this->messageDelivery->message->id ?? 'null',
                'message_name' => $this->messageDelivery->message->name ?? 'null',
            ]);

            // Check if it's time to send (respect scheduled time)
            if ($this->messageDelivery->sent_at && $this->messageDelivery->sent_at->isFuture()) {
                Log::info('⏰ Message delivery not yet time to send, releasing job', [
                    'delivery_id' => $this->messageDelivery->id,
                    'scheduled_time' => $this->messageDelivery->sent_at,
                    'current_time' => now(),
                ]);
                // Release this job to be retried later
                $delay = $this->messageDelivery->sent_at->diffInSeconds(now());
                $this->release($delay);

                return;
            }

            // Check if contact exists and has email
            if (! $this->messageDelivery->contact || ! $this->messageDelivery->contact->email) {
                Log::warning('Message delivery skipped: No contact or email', [
                    'delivery_id' => $this->messageDelivery->id,
                    'contact_id' => $this->messageDelivery->contact_id,
                ]);
                $this->messageDelivery->markAsError();

                return;
            }

            // Check if message is still active
            if (! $this->messageDelivery->message || $this->messageDelivery->message->status_id != 1) {
                Log::info('Message delivery cancelled: Message not active', [
                    'delivery_id' => $this->messageDelivery->id,
                    'message_id' => $this->messageDelivery->message_id,
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
            if (! filter_var($this->messageDelivery->contact->email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Message delivery skipped: Invalid email address', [
                    'delivery_id' => $this->messageDelivery->id,
                    'email' => $this->messageDelivery->contact->email,
                ]);
                $this->messageDelivery->markAsError();

                return;
            }

            // Mark as sending
            $this->messageDelivery->update(['status_id' => 3]); // 3 = sending

            // Get email provider from configuration
            $emailProvider = config('services.email.provider', 'smtp');
            $fallbackToSmtp = config('services.email.fallback_to_smtp', true);

            Log::info('🔧 SendMessageCampaignJob: Email provider configuration', [
                'delivery_id' => $this->messageDelivery->id,
                'email_provider' => $emailProvider,
                'fallback_to_smtp' => $fallbackToSmtp,
            ]);

            // Send email based on configured provider
            switch ($emailProvider) {
                case 'mailbaby':
                    if (config('services.mailbaby.enabled', false) && config('services.mailbaby.api_key')) {
                        $this->sendViaMailBaby();
                        break;
                    } elseif ($fallbackToSmtp) {
                        Log::warning('MailBaby not configured, falling back to SMTP', [
                            'delivery_id' => $this->messageDelivery->id,
                        ]);
                        $this->sendViaSmtp();
                        break;
                    } else {
                        throw new \Exception('MailBaby provider selected but not configured');
                    }

                case 'mailgun':
                    if (config('services.mailgun.secret')) {
                        $this->sendViaMailgun();
                        break;
                    } elseif ($fallbackToSmtp) {
                        Log::warning('Mailgun not configured, falling back to SMTP', [
                            'delivery_id' => $this->messageDelivery->id,
                        ]);
                        $this->sendViaSmtp();
                        break;
                    } else {
                        throw new \Exception('Mailgun provider selected but not configured');
                    }

                case 'smtp':
                default:
                    $this->sendViaSmtp();
                    break;
            }

            Log::info('Message delivery sent successfully', [
                'delivery_id' => $this->messageDelivery->id,
                'contact_email' => $this->messageDelivery->contact->email,
                'message_id' => $this->messageDelivery->message_id,
                'scheduled_time' => $this->messageDelivery->sent_at,
                'actual_send_time' => now(),
                'delivery_confirmation' => 'Will be confirmed via webhook',
            ]);

        } catch (\Exception $e) {
            Log::error('❌ SendMessageCampaignJob: Failed to send message delivery', [
                'delivery_id' => $this->messageDelivery->id,
                'team_id' => $this->messageDelivery->team->id ?? 'null',
                'team_name' => $this->messageDelivery->team->name ?? 'null',
                'contact_email' => $this->messageDelivery->contact->email ?? 'null',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'exception_class' => get_class($e),
                // Current mail configuration at time of error
                'current_smtp_host' => config('mail.mailers.smtp.host'),
                'current_smtp_username' => config('mail.mailers.smtp.username'),
                'current_from_address' => config('mail.from.address'),
                'team_has_custom_smtp' => $this->messageDelivery->team->hasOutgoingEmailConfig() ?? false,
                'trace' => $e->getTraceAsString(),
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
     * @return void
     */
    public function failed(\Exception $exception)
    {
        Log::error('Message delivery job failed permanently', [
            'delivery_id' => $this->messageDelivery->id,
            'error' => $exception->getMessage(),
        ]);

        // Mark as permanently failed
        $this->messageDelivery->markAsError();
    }

    /**
     * Send email via MailBaby API
     */
    protected function sendViaMailBaby()
    {
        Log::info('📧 SendMessageCampaignJob: Using MailBaby API', [
            'delivery_id' => $this->messageDelivery->id,
            'contact_email' => $this->messageDelivery->contact->email,
        ]);

        $mailBabyMail = new MailBabyMail($this->messageDelivery);
        $result = $mailBabyMail->sendViaMailBaby();

        if (! $result['success']) {
            throw new \Exception('MailBaby sending failed: '.($result['error'] ?? 'Unknown error'));
        }

        Log::info('✅ SendMessageCampaignJob: Email sent via MailBaby', [
            'delivery_id' => $this->messageDelivery->id,
            'provider_message_id' => $result['provider_message_id'],
            'contact_email' => $this->messageDelivery->contact->email,
        ]);

        // MailBaby handles status updates via webhooks
    }

    /**
     * Send email via Mailgun API
     */
    protected function sendViaMailgun()
    {
        Log::info('📧 SendMessageCampaignJob: Using Mailgun API', [
            'delivery_id' => $this->messageDelivery->id,
            'mailgun_domain' => config('services.mailgun.domain'),
            'contact_email' => $this->messageDelivery->contact->email,
        ]);

        $this->configureMailForTeam($this->messageDelivery->team);

        // Send via Mailgun and capture response
        $sentMessage = Mail::mailer('mailgun')
            ->to($this->messageDelivery->contact->email)
            ->send(new MessageDeliveryMail($this->messageDelivery));

        // Extract Message ID from Mailgun response
        $providerMessageId = null;
        if ($sentMessage && method_exists($sentMessage, 'getMessageId')) {
            $providerMessageId = $sentMessage->getMessageId();
        } elseif ($sentMessage && method_exists($sentMessage, 'getId')) {
            $providerMessageId = $sentMessage->getId();
        }

        Log::info('✅ SendMessageCampaignJob: Email sent via Mailgun', [
            'delivery_id' => $this->messageDelivery->id,
            'contact_email' => $this->messageDelivery->contact->email,
            'provider_message_id' => $providerMessageId,
        ]);

        // Mark as sent with provider message ID
        $this->messageDelivery->update([
            'email_provider' => 'mailgun',
            'provider_message_id' => $providerMessageId,
            'sent_at' => now(),
            'status_id' => 2, // 2 = sent
        ]);
    }

    /**
     * Send email via SMTP with team configuration
     */
    protected function sendViaSmtp()
    {
        Log::info('📧 SendMessageCampaignJob: Using SMTP', [
            'delivery_id' => $this->messageDelivery->id,
            'team_id' => $this->messageDelivery->team->id,
            'team_name' => $this->messageDelivery->team->name,
            'team_has_custom_smtp' => $this->messageDelivery->team->hasOutgoingEmailConfig(),
            'before_config_host' => config('mail.mailers.smtp.host'),
            'before_config_username' => config('mail.mailers.smtp.username'),
        ]);

        $this->configureMailForTeam($this->messageDelivery->team);

        Log::info('✅ SendMessageCampaignJob: SMTP configured, about to send email', [
            'delivery_id' => $this->messageDelivery->id,
            'contact_email' => $this->messageDelivery->contact->email,
            'after_config_host' => config('mail.mailers.smtp.host'),
            'after_config_username' => config('mail.mailers.smtp.username'),
            'after_config_from_address' => config('mail.from.address'),
            'after_config_from_name' => config('mail.from.name'),
        ]);

        // Send the email
        Mail::to($this->messageDelivery->contact->email)
            ->send(new MessageDeliveryMail($this->messageDelivery));

        Log::info('✅ SendMessageCampaignJob: Email sent via SMTP', [
            'delivery_id' => $this->messageDelivery->id,
            'contact_email' => $this->messageDelivery->contact->email,
        ]);

        // Mark as sent
        $this->messageDelivery->update([
            'email_provider' => 'smtp',
            'sent_at' => now(),
            'status_id' => 2, // 2 = sent
        ]);
    }
}
