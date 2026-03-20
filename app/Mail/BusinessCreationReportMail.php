<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

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
        $normalizedSummary = trim((string) $summary);
        if (
            Str::startsWith($normalizedSummary, 'Error al generar el resumen.')
            || Str::startsWith($normalizedSummary, 'No se pudo generar el resumen.')
        ) {
            $normalizedSummary = '';
        }
        $this->summary = $normalizedSummary !== '' ? $normalizedSummary : null;
        $this->insights = $insights;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Tu informe de negocio').' - '.config('app.name'),
        );
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
