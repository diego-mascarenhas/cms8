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
use Mailgun\Mailgun;

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

            Log::info('📊 SendMessageCampaignJob: Data loaded', [
                'delivery_id' => $this->messageDelivery->id,
                'team_id' => $this->messageDelivery->team->id ?? 'null',
                'team_name' => $this->messageDelivery->team->name ?? 'null',
                'contact_email' => $this->messageDelivery->contact->email ?? 'null',
                'message_id' => $this->messageDelivery->message->id ?? 'null',
                'message_name' => $this->messageDelivery->message->name ?? 'null',
            ]);

            // Check if it's time to send (respect scheduled time)
            if ($this->messageDelivery->sent_at && $this->messageDelivery->sent_at->isFuture())
            {
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
            if (! $this->messageDelivery->contact || ! $this->messageDelivery->contact->email)
            {
                Log::warning('Message delivery skipped: No contact or email', [
                    'delivery_id' => $this->messageDelivery->id,
                    'contact_id' => $this->messageDelivery->contact_id,
                ]);
                $this->messageDelivery->markAsError('No contact or email address available');

                return;
            }

            // Check if message is still active
            if (! $this->messageDelivery->message || $this->messageDelivery->message->status_id != 1)
            {
                Log::info('Message delivery cancelled: Message not active', [
                    'delivery_id' => $this->messageDelivery->id,
                    'message_id' => $this->messageDelivery->message_id,
                ]);

                return;
            }

            // Check if already delivered
            if ($this->messageDelivery->delivered_at)
            {
                Log::info('Message delivery already sent, skipping', [
                    'delivery_id' => $this->messageDelivery->id,
                    'delivered_at' => $this->messageDelivery->delivered_at,
                ]);

                return;
            }

            // Validate contact email
            if (! filter_var($this->messageDelivery->contact->email, FILTER_VALIDATE_EMAIL))
            {
                Log::warning('Message delivery skipped: Invalid email address', [
                    'delivery_id' => $this->messageDelivery->id,
                    'email' => $this->messageDelivery->contact->email,
                ]);
                $this->messageDelivery->markAsError('Invalid email address: '.$this->messageDelivery->contact->email);

                return;
            }

            // Mark as sending
            $this->messageDelivery->update(['status_id' => 2]); // 2 = sending

            // Determine email provider based on configuration
            $emailProvider = env('EMAIL_PROVIDER', 'smtp');
            $mailbabyEnabled = config('services.mailbaby.enabled', false);
            $mailbabyApiKey = config('services.mailbaby.api_key');
            $mailgunSecret = config('services.mailgun.secret');
            $fallbackToSmtp = env('EMAIL_FALLBACK_TO_SMTP', true);

            Log::info('🔧 SendMessageCampaignJob: Email provider configuration', [
                'delivery_id' => $this->messageDelivery->id,
                'email_provider' => $emailProvider,
                'mailbaby_enabled' => $mailbabyEnabled,
                'mailbaby_has_api_key' => ! empty($mailbabyApiKey),
                'mailgun_has_secret' => ! empty($mailgunSecret),
                'fallback_to_smtp' => $fallbackToSmtp,
            ]);

            // Respect EMAIL_PROVIDER configuration
            switch ($emailProvider)
            {
                case 'mailbaby':
                    if ($mailbabyEnabled && $mailbabyApiKey)
                    {
                        try
                        {
                            $this->sendViaMailBaby();
                            break;
                        } catch (\Exception $e)
                        {
                            Log::warning('MailBaby API failed, falling back to SMTP', [
                                'delivery_id' => $this->messageDelivery->id,
                                'error' => $e->getMessage(),
                            ]);
                            if ($fallbackToSmtp)
                            {
                                $this->sendViaSmtp();
                                break;
                            }
                            throw $e;
                        }
                    } else
                    {
                        Log::warning('MailBaby provider selected but not configured', [
                            'delivery_id' => $this->messageDelivery->id,
                        ]);
                        if ($fallbackToSmtp)
                        {
                            $this->sendViaSmtp();
                            break;
                        }
                        throw new \Exception('MailBaby provider selected but not configured');
                    }

                case 'mailgun':
                    if ($mailgunSecret)
                    {
                        try
                        {
                            $this->sendViaMailgun();
                            break;
                        } catch (\Exception $e)
                        {
                            Log::warning('Mailgun failed, falling back to SMTP', [
                                'delivery_id' => $this->messageDelivery->id,
                                'error' => $e->getMessage(),
                            ]);
                            if ($fallbackToSmtp)
                            {
                                $this->sendViaSmtp();
                                break;
                            }
                            throw $e;
                        }
                    } else
                    {
                        Log::warning('Mailgun provider selected but not configured', [
                            'delivery_id' => $this->messageDelivery->id,
                        ]);
                        if ($fallbackToSmtp)
                        {
                            $this->sendViaSmtp();
                            break;
                        }
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
        } catch (\Exception $e)
        {
            $errorMessage = $e->getMessage();

            Log::error('❌ SendMessageCampaignJob: Failed to send message delivery', [
                'delivery_id' => $this->messageDelivery->id,
                'team_id' => $this->messageDelivery->team->id ?? 'null',
                'team_name' => $this->messageDelivery->team->name ?? 'null',
                'contact_email' => $this->messageDelivery->contact->email ?? 'null',
                'error_message' => $errorMessage,
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'exception_class' => get_class($e),
                // Current mail configuration at time of error
                'current_smtp_host' => config('mail.mailers.smtp.host'),
                'current_smtp_username' => config('mail.mailers.smtp.username'),
                'current_from_address' => config('mail.from.address'),
                'team_has_custom_smtp' => $this->messageDelivery->team->hasOutgoingEmailConfig() ?? false,
                'is_critical_error' => \App\Models\Message::isCriticalError($errorMessage),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed with error message
            $this->messageDelivery->markAsError($errorMessage);

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
        $errorMessage = $exception->getMessage();

        Log::error('Message delivery job failed permanently', [
            'delivery_id' => $this->messageDelivery->id,
            'error' => $errorMessage,
            'is_critical_error' => \App\Models\Message::isCriticalError($errorMessage),
        ]);

        // Mark as permanently failed with error message
        $this->messageDelivery->markAsError($errorMessage);
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

        if (! $result['success'])
        {
            // Check if it's an API key error (401) - these should fallback to SMTP
            $errorMessage = $result['error'] ?? 'Unknown error';
            if (strpos($errorMessage, '"code":401') !== false || strpos($errorMessage, 'API key') !== false)
            {
                Log::warning('MailBaby API key invalid, will fallback to SMTP', [
                    'delivery_id' => $this->messageDelivery->id,
                    'error' => $errorMessage,
                ]);
                throw new \Exception('MailBaby API key invalid: '.$errorMessage);
            }

            // For other errors, also try fallback
            throw new \Exception('MailBaby sending failed: '.$errorMessage);
        }

        Log::info('✅ SendMessageCampaignJob: Email sent via MailBaby', [
            'delivery_id' => $this->messageDelivery->id,
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'contact_email' => $this->messageDelivery->contact->email,
        ]);

        // Mark as sent AND delivered (since we don't wait for webhooks)
        $this->messageDelivery->update([
            'email_provider' => 'mailbaby',
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'sent_at' => now(),
            'delivered_at' => now(), // Mark as delivered immediately
            'delivery_status' => 'delivered', // Set delivery status
            'status_id' => 3, // 3 = delivered (instead of 2 = sent)
        ]);

        Log::info('📬 SendMessageCampaignJob: Marked as delivered', [
            'delivery_id' => $this->messageDelivery->id,
            'contact_email' => $this->messageDelivery->contact->email,
            'delivery_method' => 'mailbaby_immediate',
        ]);
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

        // Use Mailgun SDK directly for better control over response
        try
        {
            $mgClient = Mailgun::create(config('services.mailgun.secret'));
            $domain = config('services.mailgun.domain');

            // Render the email content using the Mailable
            $mail = new MessageDeliveryMail($this->messageDelivery);
            $renderedContent = $mail->render();

            Log::info('🔧 SendMessageCampaignJob: Content rendered for Mailgun', [
                'delivery_id' => $this->messageDelivery->id,
                'message_name' => $this->messageDelivery->message->name,
                'raw_content_length' => strlen($this->messageDelivery->message->content ?? ''),
                'rendered_content_length' => strlen($renderedContent ?? ''),
                'has_html_content' => ! empty($renderedContent),
                'from_config' => config('mail.from.name').' <'.config('mail.from.address').'>',
            ]);

            // Send via Mailgun SDK with tracking enabled
            $result = $mgClient->messages()->send($domain, [
                'from' => config('mail.from.name').' <'.config('mail.from.address').'>',
                'to' => $this->messageDelivery->contact->email,
                'subject' => $this->messageDelivery->message->name,  // ✅ Fixed: use 'name' not 'subject'
                'html' => $renderedContent,
                'o:tracking' => 'yes',		   // ✅ Enable open tracking
                'o:tracking-clicks' => 'yes',   // ✅ Enable click tracking
                'o:tracking-opens' => 'yes',	// ✅ Enable open tracking (explicit)
            ]);

            // Extract real Message ID from Mailgun response
            $providerMessageId = $result->getId();

            Log::info('✅ SendMessageCampaignJob: Email sent via Mailgun SDK', [
                'delivery_id' => $this->messageDelivery->id,
                'contact_email' => $this->messageDelivery->contact->email,
                'provider_message_id' => $providerMessageId,
                'mailgun_response' => $result->getMessage(),
            ]);

            // Mark as sent AND delivered (since we don't wait for webhooks)
            $this->messageDelivery->update([
                'email_provider' => 'mailgun',
                'provider_message_id' => $providerMessageId,
                'sent_at' => now(),
                'delivered_at' => now(), // Mark as delivered immediately
                'delivery_status' => 'delivered', // Set delivery status
                'status_id' => 3, // 3 = delivered (instead of 2 = sent)
            ]);

            Log::info('📬 SendMessageCampaignJob: Marked as delivered', [
                'delivery_id' => $this->messageDelivery->id,
                'contact_email' => $this->messageDelivery->contact->email,
                'delivery_method' => 'mailgun_immediate',
            ]);
        } catch (\Exception $e)
        {
            Log::error('Mailgun SDK failed, falling back to Laravel Mail', [
                'delivery_id' => $this->messageDelivery->id,
                'error' => $e->getMessage(),
            ]);

            // ✅ Fix: Ensure fallback uses correct Mailgun domain
            $originalFromAddress = config('mail.from.address');
            $originalFromName = config('mail.from.name');

            // Temporarily set Mailgun domain for fallback
            config(['mail.from.address' => 'no-reply@idoneo.dev']);
            config(['mail.from.name' => 'REVISION ALPHA Emailer']);

            // Fallback to Laravel Mail
            Mail::mailer('mailgun')
                ->to($this->messageDelivery->contact->email)
                ->send(new MessageDeliveryMail($this->messageDelivery));

            // Restore original config
            config(['mail.from.address' => $originalFromAddress]);
            config(['mail.from.name' => $originalFromName]);

            // Use fallback tracking method
            $fallbackId = $this->messageDelivery->id.'-'.time().'@fallback';

            Log::info('✅ SendMessageCampaignJob: Email sent via Mailgun fallback', [
                'delivery_id' => $this->messageDelivery->id,
                'contact_email' => $this->messageDelivery->contact->email,
                'from_address_used' => 'no-reply@idoneo.dev',
                'fallback_message_id' => $fallbackId,
            ]);

            $this->messageDelivery->update([
                'email_provider' => 'mailgun',
                'provider_message_id' => $fallbackId,
                'sent_at' => now(),
                'delivered_at' => now(), // Mark as delivered immediately
                'delivery_status' => 'delivered', // Set delivery status
                'status_id' => 3, // 3 = delivered (instead of 2 = sent)
            ]);

            Log::info('📬 SendMessageCampaignJob: Marked as delivered', [
                'delivery_id' => $this->messageDelivery->id,
                'contact_email' => $this->messageDelivery->contact->email,
                'delivery_method' => 'mailgun_fallback_immediate',
            ]);
        }
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

        // Send the email with detailed error logging
        try
        {
            Log::info('📤 SendMessageCampaignJob: About to send email', [
                'delivery_id' => $this->messageDelivery->id,
                'contact_email' => $this->messageDelivery->contact->email,
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'smtp_host' => config('mail.mailers.smtp.host'),
                'smtp_username' => config('mail.mailers.smtp.username'),
            ]);

            Mail::to($this->messageDelivery->contact->email)
                ->send(new MessageDeliveryMail($this->messageDelivery));

            Log::info('✅ SendMessageCampaignJob: Email sent via SMTP successfully', [
                'delivery_id' => $this->messageDelivery->id,
                'contact_email' => $this->messageDelivery->contact->email,
            ]);

            // Mark as sent AND delivered (since we don't have webhook confirmation)
            $this->messageDelivery->update([
                'email_provider' => 'smtp',
                'sent_at' => now(),
                'delivered_at' => now(), // Mark as delivered immediately for SMTP
                'delivery_status' => 'delivered', // Set delivery status
                'status_id' => 3, // 3 = delivered (instead of 2 = sent)
            ]);

            Log::info('📬 SendMessageCampaignJob: Marked as delivered', [
                'delivery_id' => $this->messageDelivery->id,
                'contact_email' => $this->messageDelivery->contact->email,
                'delivery_method' => 'smtp_immediate',
            ]);
        } catch (\Exception $e)
        {
            $errorMessage = $e->getMessage();

            Log::error('❌ SendMessageCampaignJob: SMTP email failed', [
                'delivery_id' => $this->messageDelivery->id,
                'contact_email' => $this->messageDelivery->contact->email,
                'error_message' => $errorMessage,
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'is_critical_error' => \App\Models\Message::isCriticalError($errorMessage),
                'error_trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed with error message
            $this->messageDelivery->update([
                'status_id' => 4, // 4 = failed
                'delivery_status' => 'failed',
            ]);

            // Also use our enhanced error handling
            $this->messageDelivery->markAsError($errorMessage);

            // Re-throw the exception so the job fails and goes to failed_jobs
            throw $e;
        }
    }
}
