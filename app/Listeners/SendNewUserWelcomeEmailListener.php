<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\NewUserWelcomeEmailNotifier;
use Illuminate\Auth\Events\Registered;

class SendNewUserWelcomeEmailListener
{
    /**
     * Queue welcome email for self-service and payment-link registrations.
     */
    public function handle(Registered $event): void
    {
        $user = $event->user;
        if (! $user instanceof User)
        {
            return;
        }

        NewUserWelcomeEmailNotifier::queue($user, $user->currentTeam);
    }
}
