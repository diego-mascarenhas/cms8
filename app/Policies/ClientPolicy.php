<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Enterprise;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Enterprise $client)
    {
        if ($user->hasRole('admin'))
        {
            return true;
        }

        if ($user->hasRole('colaborator'))
        {
            return $client->assigned_to == $user->id;
        }

        return false;
    }

    public function manage(User $user, Enterprise $client)
    {
        return $user->hasRole('colaborator') && $client->assigned_to == $user->id;
    }
}