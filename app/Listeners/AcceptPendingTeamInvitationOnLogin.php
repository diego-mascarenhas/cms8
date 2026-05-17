<?php

namespace App\Listeners;

use App\Actions\Jetstream\AcceptTeamInvitation;
use App\Support\PendingTeamInvitation;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AcceptPendingTeamInvitationOnLogin
{
    public function __construct(private AcceptTeamInvitation $acceptTeamInvitation) {}

    public function handle(Login $event): void
    {
        $invitation = PendingTeamInvitation::pull(request());

        if (! $invitation)
        {
            return;
        }

        try
        {
            $this->acceptTeamInvitation->accept($event->user, $invitation);
        } catch (ValidationException $e)
        {
            Log::warning('Pending team invitation could not be accepted on login', [
                'user_id' => $event->user->id,
                'errors' => $e->errors(),
            ]);
        }
    }
}
