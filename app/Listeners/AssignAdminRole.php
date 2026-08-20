<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\EnsureRegisteredUserRole;
use Illuminate\Auth\Events\Registered;

class AssignAdminRole
{
    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User)
        {
            return;
        }

        EnsureRegisteredUserRole::assignIfMissing($event->user);
    }
}
