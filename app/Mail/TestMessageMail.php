<?php

namespace App\Mail;

use App\Models\Message;
use App\Support\MessageTemplateMergeFields;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use stdClass;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class TestMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public $message;

    public $testContact;

    public $htmlContent;

    /**
     * Create a new message instance.
     */
    public function __construct(Message $message, stdClass $testContact, string $htmlContent)
    {
        $this->message = $message;
        $this->testContact = $testContact;
        $this->htmlContent = $htmlContent;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $team = auth()->user()->currentTeam;

        // Add advertising footer if team is using system SMTP
        $finalHtml = $this->htmlContent;
        $advertisingFooter = $team ? $team->getAdvertisingFooter() : '';

        if ($advertisingFooter)
        {
            if (stripos($finalHtml, '</body>') !== false)
            {
                $finalHtml = str_ireplace('</body>', $advertisingFooter.'</body>', $finalHtml);
            } else
            {
                $finalHtml .= $advertisingFooter;
            }
        }

        // Get CSS from template if available
        $css = $this->message->resolveMailCss();

        // Inline CSS styles
        $cssInliner = new CssToInlineStyles;
        $inlinedHtml = $cssInliner->convert($finalHtml, $css);

        // Use from address/name already configured by ConfiguresTeamMail trait
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        $subject = MessageTemplateMergeFields::replace((string) $this->message->name, $this->testContact);

        return $this->from($fromAddress, $fromName)
            ->subject('[TEST] '.$subject)
            ->html($inlinedHtml);
    }
}
