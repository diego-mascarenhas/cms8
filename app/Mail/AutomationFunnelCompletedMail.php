<?php

namespace App\Mail;

use App\Models\Automation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AutomationFunnelCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $summaryLines
     * @param  list<array{role: string, content: string}>  $conversationExcerpts
     */
    public function __construct(
        public Automation $automation,
        public string $recipientName,
        public array $summaryLines = [],
        public array $conversationExcerpts = [],
        public ?string $teamName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Resumen del embudo: :name', ['name' => $this->automation->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.automation-funnel-completed',
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
