<?php

namespace App\Mail;

use App\Models\MessageDelivery;
use App\Services\MailBabyService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class MailBabyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $delivery;

    private $mailBabyService;

    public function __construct(MessageDelivery $delivery)
    {
        $this->delivery = $delivery;
        $this->mailBabyService = app(MailBabyService::class);
    }

    public function build()
    {
        // This method won't be used for actual sending,
        // but Laravel requires it for the Mailable interface
        return $this->subject('Newsletter')
            ->html('<p>This email is sent via MailBaby API</p>');
    }

    /**
     * Send email using MailBaby API instead of Laravel's mail system
     */
    public function sendViaMailBaby()
    {
        try
        {
            $subject = $this->delivery->getSubjectForContact();
            $html = $this->delivery->getHtmlForContact();

            // Add advertising footer if team is using system SMTP
            $advertisingFooter = config('app.mail_advertising_footer', '');
            if ($advertisingFooter)
            {
                $html .= $advertisingFooter;
            }

            // Inline CSS
            $css = '';
            $inliner = new CssToInlineStyles;
            $htmlInlined = $inliner->convert($html, $css);

            // Get Mailer campaign sender (override or team fallback)
            $team = $this->delivery->team ?? auth()->user()->currentTeam;
            $sender = $team->getMailerEmailSender();
            $fromAddress = $sender['from_address'];
            $fromName = $sender['from_name'];

            if ($fromAddress === '' || $fromName === '')
            {
                throw new \RuntimeException('Mailer sender not configured for this team');
            }

            // Prepare email data for MailBaby API
            $emailData = [
                'to' => $this->delivery->contact->email,
                'from' => $fromName.' <'.$fromAddress.'>',
                'subject' => $subject,
                'body' => $htmlInlined,
                'message_id' => $this->delivery->id, // Use our delivery ID for tracking
            ];

            // Send via MailBaby API
            $result = $this->mailBabyService->sendEmail($emailData);

            if ($result['success'])
            {
                // Update delivery with provider info for webhook tracking
                $this->delivery->update([
                    'email_provider' => 'mailbaby',
                    'provider_message_id' => $result['message_id'],
                    'sent_at' => now(),
                    'status_id' => 2, // sent
                ]);

                Log::info('MailBaby: Email sent successfully', [
                    'delivery_id' => $this->delivery->id,
                    'provider_message_id' => $result['message_id'],
                    'contact_email' => $this->delivery->contact->email,
                ]);

                return [
                    'success' => true,
                    'provider_message_id' => $result['message_id'],
                ];
            } else
            {
                Log::error('MailBaby: Failed to send email', [
                    'delivery_id' => $this->delivery->id,
                    'contact_email' => $this->delivery->contact->email,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                // Mark as failed
                $this->delivery->update([
                    'status_id' => 4, // failed
                    'delivery_status' => 'failed',
                ]);

                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Unknown error',
                ];
            }
        } catch (\Exception $e)
        {
            Log::error('MailBaby: Exception in sendViaMailBaby', [
                'delivery_id' => $this->delivery->id,
                'error' => $e->getMessage(),
            ]);

            // Mark as failed
            $this->delivery->update([
                'status_id' => 4, // failed
                'delivery_status' => 'failed',
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
