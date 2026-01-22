<?php

namespace App\Mail;

use App\Models\Team;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AutologinInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $team;
    public $autologinUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Team $team, string $autologinUrl)
    {
        $this->user = $user;
        $this->team = $team;
        $this->autologinUrl = $autologinUrl;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Acceso a tu cuenta - '.$this->team->name)
            ->view('emails.autologin-invitation')
            ->with([
                'user' => $this->user,
                'team' => $this->team,
                'autologinUrl' => $this->autologinUrl,
            ]);
    }
}
