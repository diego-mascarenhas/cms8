<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Conversation;

class IncomingMessageNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $conversation;

    /**
     * Create a new message instance.
     *
     * @param  Conversation  $conversation
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