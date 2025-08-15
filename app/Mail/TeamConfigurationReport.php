<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamConfigurationReport extends Mailable
{
    use Queueable, SerializesModels;

    public $teamResult;
    public $failuresOnly;

    /**
     * Create a new message instance.
     */
    public function __construct($teamResult, $failuresOnly = false)
    {
        $this->teamResult = $teamResult;
        $this->failuresOnly = $failuresOnly;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $teamName = $this->teamResult['team_name'];
        $failedCount = $this->teamResult['summary']['failed'];

        if ($failedCount > 0) {
            $subject = "⚠️ Configuration Issues - {$teamName}";
        } else {
            $subject = "✅ Configuration Report - {$teamName}";
        }

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            text: 'emails.team-configuration-report-text',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
