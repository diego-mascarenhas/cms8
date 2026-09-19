<?php

namespace App\Mail;

use App\Models\Communication;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommunicationMail extends Mailable
{
    use SerializesModels;

    public function __construct(public Communication $communication) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->communication->subject ?: __('Communications'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.communication',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->communication->getMedia('attachments') as $media)
        {
            $path = $media->getPath();
            if (! is_string($path) || $path === '' || ! file_exists($path))
            {
                continue;
            }

            $attachments[] = Attachment::fromPath($path)
                ->as($media->file_name)
                ->withMime($media->mime_type);
        }

        return $attachments;
    }
}
