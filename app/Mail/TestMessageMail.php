<?php

namespace App\Mail;

use App\Models\Message;
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
        $emailConfig = $team->getOutgoingEmailConfig();

        // Get CSS from template if available
        $css = '';
        if ($this->message->template && isset($this->message->template->gjs_data['css'])) {
            $css = $this->message->template->gjs_data['css'];
        }

        // Inline CSS styles
        $cssInliner = new CssToInlineStyles();
        $inlinedHtml = $cssInliner->convert($this->htmlContent, $css);

        return $this->from($emailConfig['from_address'], $emailConfig['from_name'])
                    ->subject('[TEST] ' . $this->message->name)
                    ->html($inlinedHtml);
    }
}
