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
            $this->messageDelivery->refresh();
            $this->messageDelivery->load(['contact', 'message', 'message.template', 'team']);

            if ($this->messageDelivery->scheduled_for && $this->messageDelivery->scheduled_for->isFuture())
            {
                $delay = $this->messageDelivery->scheduled_for->diffInSeconds(now());
                $this->release($delay);

                return;
            }

            if (! $this->validateDelivery())
            {
                return;
            }

            $this->messageDelivery->update(['status_id' => 2]);

            $this->sendEmail();
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
        if (! $this->messageDelivery->contact || ! $this->messageDelivery->contact->email)
        {
            Log::warning('Message delivery skipped: No contact or email', [
                'delivery_id' => $this->messageDelivery->id,
            ]);
            $this->messageDelivery->markAsError('No contact or email address available');

            return false;
        }

        if (! $this->messageDelivery->message)
        {
            Log::info('Message delivery skipped: message missing', [
                'delivery_id' => $this->messageDelivery->id,
            ]);

            return false;
        }

        if (! $this->messageDelivery->message->status_id)
        {
            Log::info('Message delivery skipped: message is paused or inactive', [
                'delivery_id' => $this->messageDelivery->id,
                'message_id' => $this->messageDelivery->message->id,
            ]);

            return false;
        }

        if ($this->messageDelivery->delivered_at)
        {
            return false;
        }

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

        // #region agent log
        $this->agentDebugNdjson('SendMessageCampaignJob::sendEmail', 'send_email_branch', [
            'hypothesisId' => 'H5',
            'delivery_id' => (int) $this->messageDelivery->id,
            'mailbaby_enabled' => (bool) $mailBabyEnabled,
            'mailbaby_has_api_key' => (bool) config('services.mailbaby.api_key'),
        ]);
        // #endregion

        if ($mailBabyEnabled && config('services.mailbaby.api_key'))
        {
            try
            {
                $this->sendViaMailBabyApi();

                return;
            } catch (\Exception $e)
            {
                Log::warning('MailBaby API failed, falling back to SMTP', [
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
        $mailBabyService = app(\App\Services\MailBabyService::class);

        $htmlContent = $this->messageDelivery->getHtmlForContact();

        $fromName = config('mail.from.name');
        $fromEmail = config('mail.from.address');

        $emailData = [
            'to' => $this->messageDelivery->contact->email,
            'from' => $fromName.' <'.$fromEmail.'>',
            'subject' => $this->messageDelivery->getSubjectForContact(),
            'body' => $htmlContent,
            'message_id' => $this->messageDelivery->id,
        ];

        $result = $mailBabyService->sendEmail($emailData);

        if (! $result['success'])
        {
            throw new \Exception('MailBaby API request failed: '.($result['error'] ?? 'Unknown error'));
        }

        $this->messageDelivery->update([
            'email_provider' => 'mailbaby',
            'provider_message_id' => $result['message_id'] ?? null,
            'sent_at' => now(),
            'delivery_status' => 'sent',
            'status_id' => 2,
            'provider_data' => $result['data'] ?? null,
        ]);

        $this->messageDelivery->team->incrementEmailUsage();
    }

    /**
     * Send email via SMTP
     */
    private function sendViaSmtp()
    {
        $this->configureMailForTeam($this->messageDelivery->team);

        // #region agent log
        $toEmail = (string) ($this->messageDelivery->contact->email ?? '');
        $toDomain = str_contains($toEmail, '@') ? (string) substr(strrchr($toEmail, '@'), 1) : '';
        $this->agentDebugNdjson('SendMessageCampaignJob::sendViaSmtp', 'resolved_mail_config', [
            'hypothesisId' => 'H1',
            'delivery_id' => (int) $this->messageDelivery->id,
            'default_mailer' => (string) config('mail.default'),
            'smtp_host' => (string) config('mail.mailers.smtp.host'),
            'smtp_port' => (int) config('mail.mailers.smtp.port'),
            'smtp_encryption' => config('mail.mailers.smtp.encryption'),
            'team_has_custom_outgoing' => $this->messageDelivery->team->hasOutgoingEmailConfig(),
            'to_domain' => $toDomain,
        ]);
        // #endregion

        $mailableClass = config('humano-mailer.mailables.message_delivery_mail', \App\Mail\MessageDeliveryMail::class);

        if (! class_exists($mailableClass))
        {
            throw new \Exception("Mailable class {$mailableClass} not found");
        }

        $mailable = new $mailableClass($this->messageDelivery);

        Mail::to($this->messageDelivery->contact->email)->sendNow($mailable);

        // #region agent log
        $this->agentDebugNdjson('SendMessageCampaignJob::sendViaSmtp', 'after_mail_send', [
            'hypothesisId' => 'H3',
            'delivery_id' => (int) $this->messageDelivery->id,
            'mail_send_returned' => true,
        ]);
        // #endregion

        $this->messageDelivery->update([
            'email_provider' => 'smtp',
            'sent_at' => now(),
            'delivered_at' => now(),
            'delivery_status' => 'delivered',
            'status_id' => 3,
        ]);
    }

    /**
     * Handle job errors
     */
    private function handleError(\Exception $e)
    {
        $errorMessage = $e->getMessage();

        Log::error('SendMessageCampaignJob failed', [
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
        Log::error('SendMessageCampaignJob failed permanently', [
            'delivery_id' => $this->messageDelivery->id,
            'error' => $exception->getMessage(),
        ]);

        $this->messageDelivery->markAsError($exception->getMessage());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function agentDebugNdjson(string $location, string $message, array $data): void
    {
        $path = base_path('.cursor/debug-ca54fc.log');
        $payload = array_merge([
            'sessionId' => 'ca54fc',
            'timestamp' => (int) round(microtime(true) * 1000),
            'location' => $location,
            'message' => $message,
        ], $data);
        @file_put_contents($path, json_encode($payload)."\n", FILE_APPEND | LOCK_EX);
    }
}
