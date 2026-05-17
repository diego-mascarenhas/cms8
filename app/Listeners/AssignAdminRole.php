<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;

class AssignAdminRole
{
    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if ($user->roles()->exists())
        {
            return;
        }

        if ($user->ownedTeams()->where('personal_team', true)->exists())
        {
            $user->assignRole('admin');

            return;
        }

        $user->assignRole('user');
    }
}
