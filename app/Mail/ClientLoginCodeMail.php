<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientLoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $email, public string $code)
    {
        $this->to($email);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu código de acceso',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-login-code',
        );
    }
}
