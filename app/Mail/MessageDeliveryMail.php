<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\MessageDelivery;

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
        $html = $this->delivery->getHtmlForContact();
        return $this->subject($subject)
            ->html($html);
    }
}
