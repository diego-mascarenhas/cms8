<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessCreationReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<string, mixed> */
    public array $config;

    public ?string $summary;

    /** @var array<string, mixed> */
    public array $insights;

    /**
     * Create a new message instance.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $insights
     */
    public function __construct(array $config, ?string $summary, array $insights)
    {
        $this->config = $config;
        $this->summary = $summary;
        $this->insights = $insights;
    }

    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: __('Tu informe de negocio').' - '.config('app.name'),
        );
        $copyTo = config('mail.from.address');
        if ($copyTo && $copyTo !== 'hello@example.com')
        {
            $envelope = $envelope->bcc($copyTo);
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.business-creation-report',
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
