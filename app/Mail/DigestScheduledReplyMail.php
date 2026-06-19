<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DigestScheduledReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $mailSubject,
        public readonly string $plainBody,
    ) {}

    public function build(): self
    {
        return $this->subject($this->mailSubject)
            ->view('emails.digest-scheduled-reply')
            ->with([
                'plainBody' => $this->plainBody,
            ]);
    }
}
