<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $leadData;

    public function __construct($leadData)
    {
        $this->leadData = $leadData;
    }

    public function build()
    {
        return $this->subject('Nuevo Lead Recibido')
                    ->view('emails.lead-notification');
    }
}
