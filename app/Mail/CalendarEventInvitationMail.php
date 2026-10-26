<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CalendarEventInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $guestName,
        public string $title,
        public string $when,
        public ?string $url,
        public string $kind,
        public string $fromAddress,
        public string $fromName,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->kind === 'updated'
            ? 'Nuevo horario: '.$this->title
            : 'Reunión: '.$this->title;

        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.calendar-event-invitation',
        );
    }
}
