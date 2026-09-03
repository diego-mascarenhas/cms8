<?php

namespace App\Mail;

use App\Helpers\EmailTrackingHelper;
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
        $subject = $this->delivery->getSubjectForContact();
        $html = $this->delivery->getHtmlForContact(); // This already includes the advertising footer

        $css = '';
        $inliner = new CssToInlineStyles;
        $htmlInlined = $inliner->convert($html, $css);

        $this->delivery->loadMissing(['contact', 'message']);
        $recipient = $this->delivery->contact->email ?? null;
        $unsubscribeEnabled = (bool) ($this->delivery->message->show_unsubscribe ?? false);

        // Use config() which will be set by ConfiguresTeamMail trait before sending
        // Do NOT explicitly set ->from() here, let Laravel use the config('mail.from')
        return EmailTrackingHelper::applyListUnsubscribeHeaders($this, $recipient, $unsubscribeEnabled)
            ->subject($subject)
            ->html($htmlInlined);
    }
}
