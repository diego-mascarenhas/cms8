<?php

namespace App\Mail;

use App\Models\MessageDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Sent synchronously from {@see \App\Jobs\SendMessageCampaignJob} (that job is already queued).
 * Do not implement ShouldQueue or Mail::send() would double-queue to the default queue and never
 * run under workers that only listen to mailer/campaign.
 */
class MessageDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $delivery;

    public function __construct(MessageDelivery $delivery)
    {
        $this->delivery = $delivery;
    }

    public function build()
    {
        $subject = $this->delivery->message ? $this->delivery->message->name : 'Newsletter';
        $html = $this->delivery->getHtmlForContact(); // This already includes the advertising footer

        $css = '';
        $inliner = new CssToInlineStyles;
        $htmlInlined = $inliner->convert($html, $css);

        // Use config() which will be set by ConfiguresTeamMail trait before sending
        // Do NOT explicitly set ->from() here, let Laravel use the config('mail.from')
        return $this->subject($subject)
            ->html($htmlInlined);
    }
}
