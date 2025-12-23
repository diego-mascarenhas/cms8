<?php

namespace App\Policies;

use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     * Admins have full access to everything in their team.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null; // Continue to specific policy methods
    }

    public function view(User $user, Enterprise $client)
    {
        if ($user->hasRole('admin'))
        {
            return true;
        }

        if ($user->hasRole('collaborator'))
        {
            return $client->assigned_to == $user->id;
        }

        return false;
    }

    public function manage(User $user, Enterprise $client)
    {
        return $user->hasRole('collaborator') && $client->assigned_to == $user->id;
    }
}
