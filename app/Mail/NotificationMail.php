<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $notification;

    /**
     * Create a new message instance.
     */
    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $data = [
            'notification' => $this->notification,
            'contact' => $this->notification->contact,
            'sender' => $this->notification->user,
            'team' => $this->notification->team,
        ];

        $mail = $this->subject($this->notification->subject);

        return $mail->view('emails.notification', $data);
    }
}
