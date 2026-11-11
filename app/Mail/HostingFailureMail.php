<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HostingFailureMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $technical,
        public array $context = [],
    ) {}

    public function envelope(): Envelope
    {
        $domain = trim((string) ($this->context['domain'] ?? ''));

        return new Envelope(
            subject: $domain !== '' ? 'Error de hosting: '.$domain : 'Error de hosting',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hosting-failure',
        );
    }
}
