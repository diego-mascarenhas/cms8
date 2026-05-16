<?php

namespace App\Mail;

use App\Models\UserDailyPerformanceInsight;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyPerformanceInsightMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserDailyPerformanceInsight $insight,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('app.performance_digest_email_subject', [
                'date' => $this->insight->insight_date->format('d/m/Y'),
            ]),
        );
    }

    public function content(): Content
    {
        $highlights = $this->insight->context_snapshot['highlights'] ?? [];

        return new Content(
            markdown: 'mail.daily-performance-insight',
            with: [
                'insight' => $this->insight,
                'highlights' => $highlights,
            ],
        );
    }
}
