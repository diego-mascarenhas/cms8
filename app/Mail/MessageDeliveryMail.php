<?php

namespace App\Mail;

use App\Models\MessageDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class MessageDeliveryMail extends Mailable implements ShouldQueue
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

        // Get explicit from configuration like TestMessageMail does
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        return $this->from($fromAddress, $fromName)
            ->subject($subject)
            ->html($htmlInlined);
    }
}
