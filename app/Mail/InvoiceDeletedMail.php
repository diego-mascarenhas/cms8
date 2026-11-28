<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceDeletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $invoiceNumber,
        public string $enterpriseName,
        public string $amountLabel,
        public string $operationLabel,
        public string $teamName,
        public string $deletedByName,
        public string $deletedByEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Invoice deleted in :team', ['team' => $this->teamName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-deleted',
        );
    }
}
