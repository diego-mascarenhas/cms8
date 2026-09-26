<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
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
            from: new Address('administracion@revisionalpha.com', 'REVISION ALPHA'),
            subject: 'Acceso al Área de Clienes - REVSION ALPHA',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-login-code',
        );
    }
}
