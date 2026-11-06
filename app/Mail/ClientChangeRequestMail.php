<?php

namespace App\Mail;

use App\Helpers\Helpers;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientChangeRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $logoUrl;

    public string $appName;

    public function __construct(
        public Task $task,
        public string $body,
        public string $senderName,
        public string $taskUrl,
    ) {
        $this->logoUrl = url(Helpers::logoAsset('light'));
        $this->appName = (string) config('app.name');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Solicitud de cambio - '.$this->task->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.task-client-change',
        );
    }
}
