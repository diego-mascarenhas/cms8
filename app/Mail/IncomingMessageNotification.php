<?php

namespace App\Mail;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IncomingMessageNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $conversation;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Conversation $conversation)
    {
        $this->conversation = $conversation;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('New Message Received')
            ->view('emails.incoming-message-notification');
    }
}
