<?php

namespace App\Mail;

use App\Helpers\Helpers;
use App\Models\TeamInvitation as TeamInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TeamInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TeamInvitationModel $invitation) {}

    public function build(): static
    {
        $appName = (string) config('app.name');

        return $this->subject(__('Team Invitation'))
            ->view('emails.team-invitation')
            ->with([
                'acceptUrl' => URL::signedRoute('team-invitations.accept', [
                    'invitation' => $this->invitation,
                ]),
                'logoUrl' => url(Helpers::logoAsset('light')),
                'appName' => $appName,
            ]);
    }
}
