<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SlashLandingInterestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $leadEmail,
        public string $sourceLabel,
        public string $submittedAt,
        public ?string $leadName = null,
        public ?string $leadPhone = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('slash_landing.lead.mail_subject', ['email' => $this->leadEmail]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.slash-landing-interest',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
