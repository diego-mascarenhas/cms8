<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;

class AssignAdminRole
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(Registered $event)
    {
        $user = $event->user;
        $user->assignRole(2);
    }
}
